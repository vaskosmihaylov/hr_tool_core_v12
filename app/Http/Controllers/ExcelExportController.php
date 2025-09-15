<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Exports\VikiReportsExport;
use Maatwebsite\Excel\Facades\Excel;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\VikiUser;
use viki\Service\Http\Controllers\ReportController;
use Illuminate\Support\Facades\DB;

class ExcelExportController extends Controller
{
    public function exportReport(Request $request)
    {
        // Validate request
        $request->validate([
            'month_id' => 'required|string|size:2',
            'year_id' => 'required|string|size:4',
            'region_id' => 'array|nullable',
            'workplace_id' => 'array|nullable', 
            'client_id' => 'array|nullable',
            'worker_id' => 'nullable|integer'
        ]);

        try {
            // Re-generate the report data with fixed multi-object logic
            $reportData = $this->generateReportData($request->all());
            
            if (empty($reportData['workerRecords']) || count($reportData['workerRecords']) == 0) {
                return response('No data found for the selected criteria', 404);
            }

            $filename = 'справка_за_месец_' . $request->month_id . '-' . $request->year_id . '.xlsx';

            // Log activity
            activity()
                ->performedOn(Auth::user())
                ->causedBy(Auth::user())
                ->log('Excel експорт завършен за ' . $filename);

            return Excel::download(
                new VikiReportsExport(
                    $reportData['workerRecords'],
                    $reportData['arraySum'],
                    $reportData['bonusData'],
                    $reportData['penaltyData'],
                    $reportData['vacationData'], // NEW: Add vacation data
                    $request->month_id,
                    $request->year_id
                ),
                $filename
            );

        } catch (\Exception $e) {
            return response('Excel generation failed: ' . $e->getMessage(), 500);
        }
    }

    private function generateReportData(array $filters)
    {
        $month_id = $filters['month_id'];
        $year_id = $filters['year_id'];
        $region_id = $filters['region_id'] ?? [];
        $workplace_id = $filters['workplace_id'] ?? [];
        $client_id = $filters['client_id'] ?? [];
        $worker_id = $filters['worker_id'] ?? null;

        // Use the existing ReportController logic but with fixes
        $user = Auth::user();
        $manRegion_id = '';

        if (($user->hasRole('manager')) || ($user->hasRole('supervisor'))) {
            $manRegion_id = \viki\Service\Models\Elequent\VikiUser::getCurrentUserRegionId($user->id);
            $region_id = $manRegion_id;
        }

        // Fixed query - each worker-workplace combination should be a separate row
        $query = \viki\Service\Models\Elequent\WorkerRecord::select(
                'viki_worker_records.worker_id',
                'viki_workers.name',
                'viki_workers.family_name',
                'viki_workers.middle_name',
                'viki_workers.egn',
                'viki_worker_records.work_place_id',
                'viki_work_place.name as workPlaceName',
                'viki_work_place.client_id as clId',
                'viki_work_place.region_id as regId',
                DB::raw('sum(viki_worker_records.hours) as total'),
                DB::raw('group_concat(DISTINCT viki_worker_records.work_place_activity_id) as activities'),
                // Create unique ID for each worker-workplace combination
                DB::raw('CONCAT(viki_worker_records.worker_id, "-", viki_worker_records.work_place_id) as unique_id')
            )
            ->leftJoin('viki_workers', function($join) {
                $join->on('viki_workers.id', '=', 'viki_worker_records.worker_id');
            })
            ->leftJoin('viki_work_place', function($join) {
                $join->on('viki_work_place.id', '=', 'viki_worker_records.work_place_id');
            })
            ->where('viki_worker_records.date', 'like', $year_id . '-' . $month_id . '%');

        // Apply role-based filtering
        if ($user->hasRole('supervisor')) {
            $vikiUser = \viki\Service\Models\Elequent\VikiUser::find($user->id);
            $userWorkplaceIds = $vikiUser->workPlaces()->pluck('id')->toArray();
            $query->whereIn('viki_worker_records.work_place_id', $userWorkplaceIds);
        }

        // Apply filters
        if (!empty($workplace_id)) {
            $query->whereIn('viki_worker_records.work_place_id', $workplace_id);
        }

        if (!empty($region_id)) {
            if (!empty($manRegion_id)) {
                $region_id = $manRegion_id;
            }
            $query->whereIn('viki_work_place.region_id', $region_id);
        }

        if (!empty($client_id)) {
            $query->whereIn('viki_work_place.client_id', $client_id);
        }

        if (!empty($worker_id)) {
            $query->where('viki_worker_records.worker_id', '=', $worker_id);
        }

        // Critical fix: Group by worker AND workplace to ensure separate rows for each combination
        $query->groupBy([
            'viki_worker_records.worker_id', 
            'viki_worker_records.work_place_id',
            'viki_workers.name',
            'viki_workers.family_name',
            'viki_workers.middle_name',
            'viki_workers.egn',
            'viki_work_place.name',
            'viki_work_place.client_id',
            'viki_work_place.region_id'
        ]);
        
        $workerRecords = $query->get();

        // Calculate salaries using existing logic
        $arraySum = [];
        foreach ($workerRecords as $records) {
            $activitiesArray = explode(",", $records->activities);

            foreach ($activitiesArray as $activity) {
                $workplaceActivity = \viki\Service\Models\Elequent\WorkPlaceActivity::find($activity);
                if ($workplaceActivity !== null) {
                    $workingHours = ReportController::getActivityWorkingHoursForDate(
                        $workplaceActivity, 
                        $year_id . '-' . $month_id
                    );

                    $workPlaceActivityHourPrice = $workingHours != 0 ? 
                        ($workplaceActivity->neto_salary + $workplaceActivity->social_plus) / $workingHours : 0;
                    
                    $hoursByActivity = \viki\Service\Models\Elequent\WorkerRecord::select(
                            'viki_worker_records.work_place_activity_id', 
                            DB::raw('sum(viki_worker_records.hours) as totalHours')
                        )
                        ->where('worker_id', $records->worker_id)
                        ->where('work_place_id', $records->work_place_id) // Add workplace filter
                        ->where('work_place_activity_id', $workplaceActivity->id)
                        ->where('date', 'LIKE', $year_id . '-' . $month_id . '%')
                        ->groupBy('viki_worker_records.work_place_activity_id')
                        ->get()->toArray();
                    
                    if (!empty($hoursByActivity)) {
                        $arraySum[$records->unique_id][] = $workPlaceActivityHourPrice * $hoursByActivity[0]['totalHours'];
                    }
                }
            }
        }

        $newSumArray = [];
        if (!empty($arraySum)) {
            foreach ($arraySum as $key => $allSum) {
                $newSumArray[$key] = array_sum($allSum);
            }
        }

        // Calculate bonuses and penalties for each worker-workplace combination
        $bonusData = [];
        $penaltyData = [];
        $vacationData = []; // NEW: vacation data
        
        foreach ($workerRecords as $record) {
            // Get bonus amount (type = 0) using proper date filtering
            $bonusAmount = \viki\Service\Models\Elequent\WorkerBonus::where('worker_id', $record->worker_id)
                ->where('work_place_id', $record->work_place_id)
                ->where('type', 0) // BONUS
                ->whereYear('for_month', $year_id)
                ->whereMonth('for_month', $month_id)
                ->sum('sum');
            
            // Get penalty amount (type = 1) using proper date filtering
            $penaltyAmount = \viki\Service\Models\Elequent\WorkerBonus::where('worker_id', $record->worker_id)
                ->where('work_place_id', $record->work_place_id)
                ->where('type', 1) // PENALTY
                ->whereYear('for_month', $year_id)
                ->whereMonth('for_month', $month_id)
                ->sum('sum');

            // NEW: Get vacation data for the worker in the specified month
            $vacationDays = \viki\Service\Models\Elequent\Vacation::where('worker_id', $record->worker_id)
                ->where('status', 1) // Only approved vacations
                ->where(function($query) use ($year_id, $month_id) {
                    $startOfMonth = \Carbon\Carbon::create($year_id, $month_id, 1)->startOfMonth();
                    $endOfMonth = \Carbon\Carbon::create($year_id, $month_id, 1)->endOfMonth();
                    
                    $query->where(function($q) use ($startOfMonth, $endOfMonth) {
                        // Vacation starts within the month
                        $q->whereBetween('start_date', [$startOfMonth, $endOfMonth]);
                    })->orWhere(function($q) use ($startOfMonth, $endOfMonth) {
                        // Vacation ends within the month  
                        $q->whereBetween('end_date', [$startOfMonth, $endOfMonth]);
                    })->orWhere(function($q) use ($startOfMonth, $endOfMonth) {
                        // Vacation spans the entire month
                        $q->where('start_date', '<=', $startOfMonth)
                          ->where('end_date', '>=', $endOfMonth);
                    });
                })
                ->get();

            // Calculate actual vacation days in the month
            $totalVacationDays = 0;
            $vacationDetails = [];
            
            foreach ($vacationDays as $vacation) {
                $vacationStart = \Carbon\Carbon::parse($vacation->start_date);
                $vacationEnd = \Carbon\Carbon::parse($vacation->end_date);
                $monthStart = \Carbon\Carbon::create($year_id, $month_id, 1)->startOfMonth();
                $monthEnd = \Carbon\Carbon::create($year_id, $month_id, 1)->endOfMonth();
                
                // Calculate overlapping days within the month
                $overlapStart = $vacationStart->max($monthStart);
                $overlapEnd = $vacationEnd->min($monthEnd);
                
                if ($overlapStart <= $overlapEnd) {
                    $daysInMonth = $overlapStart->diffInDays($overlapEnd) + 1;
                    $totalVacationDays += $daysInMonth;
                    
                    $typeLabels = [
                        1 => 'Платена',
                        2 => 'Неплатена', 
                        3 => 'Болничен'
                    ];
                    
                    $vacationDetails[] = [
                        'days' => $daysInMonth,
                        'type' => $typeLabels[$vacation->type] ?? 'Неизвестен',
                        'start_date' => $overlapStart->format('d.m.Y'),
                        'end_date' => $overlapEnd->format('d.m.Y')
                    ];
                }
            }
                
            $bonusData[$record->unique_id] = $bonusAmount;
            $penaltyData[$record->unique_id] = $penaltyAmount;
            $vacationData[$record->unique_id] = [
                'total_days' => $totalVacationDays,
                'details' => $vacationDetails
            ];
        }

        return [
            'workerRecords' => $workerRecords,
            'arraySum' => $newSumArray,
            'bonusData' => $bonusData,
            'penaltyData' => $penaltyData,
            'vacationData' => $vacationData, // NEW: Add vacation data
            'summary' => [
                'total_workers' => $workerRecords->unique('worker_id')->count(),
                'total_records' => $workerRecords->count(), // NEW: Total worker-workplace combinations
                'total_hours' => $workerRecords->sum('total'),
                'total_salary' => array_sum($newSumArray),
                'total_bonus' => array_sum($bonusData),
                'total_penalty' => array_sum($penaltyData),
                'total_vacation_days' => array_sum(array_column($vacationData, 'total_days')), // NEW
            ]
        ];
    }
}
