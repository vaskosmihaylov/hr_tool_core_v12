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
            // Re-generate the report data (same logic as PDF export)
            $reportData = $this->generateReportData($request->all());
            
            if (empty($reportData['workerRecords']) || count($reportData['workerRecords']) == 0) {
                return response('No data found for the selected criteria', 404);
            }

            $filename = 'viki_справка_за_месец_' . $request->month_id . '-' . $request->year_id . '.xlsx';

            // Log activity
            activity()
                ->performedOn(Auth::user())
                ->causedBy(Auth::user())
                ->log('Excel експорт завършен за ' . $filename);

            return Excel::download(
                new VikiReportsExport(
                    $reportData['workerRecords'],
                    $reportData['arraySum'],
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

        // Use the existing ReportController logic
        $user = Auth::user();
        $manRegion_id = '';

        if (($user->hasRole('manager')) || ($user->hasRole('supervisor'))) {
            $manRegion_id = VikiUser::getCurrentUserRegionId($user->id);
            $region_id = $manRegion_id;
        }

        $query = WorkerRecord::select(
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
                DB::raw('MIN(viki_worker_records.id) as ID')
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
            $vikiUser = VikiUser::find($user->id);
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
                    
                    $hoursByActivity = WorkerRecord::select(
                            'viki_worker_records.work_place_activity_id', 
                            DB::raw('sum(viki_worker_records.hours) as totalHours')
                        )
                        ->where('worker_id', $records->worker_id)
                        ->where('work_place_activity_id', $workplaceActivity->id)
                        ->where('date', 'LIKE', $year_id . '-' . $month_id . '%')
                        ->groupBy('viki_worker_records.work_place_activity_id')
                        ->get()->toArray();
                    
                    if (!empty($hoursByActivity)) {
                        $arraySum[$records->ID][] = $workPlaceActivityHourPrice * $hoursByActivity[0]['totalHours'];
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

        return [
            'workerRecords' => $workerRecords,
            'arraySum' => $newSumArray,
            'summary' => [
                'total_workers' => $workerRecords->unique('worker_id')->count(),
                'total_hours' => $workerRecords->sum('total'),
                'total_salary' => array_sum($newSumArray),
            ]
        ];
    }
}
