<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\MonthlyPresenceExport;
use App\Services\Presence\PresenceConfigurationService;
use Maatwebsite\Excel\Facades\Excel;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\Worker;
use Viki\Service\Models\Elequent\WorkerRecord;
use Viki\Service\Models\Elequent\WorkPlaceActivity;
use Carbon\Carbon;

class PresenceExportController extends Controller
{
    public function exportMonthlyPresence(Request $request)
    {
        $workplace = $request->get('workplace');
        $year = $request->get('year') ?: Carbon::now()->year;
        $month = $request->get('month') ?: Carbon::now()->month;

        $workplaceData = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)
            ->with('region')
            ->find($workplace);

        if (!$workplaceData) {
            abort(404, 'Workplace not found');
        }

        // Ensure monthly activities exist
        PresenceConfigurationService::ensureMonthlyActivities($workplace, $year, $month);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;

        // Load monthly activity instances (NOT snapshots)
        $activities = WorkPlaceActivity::where('work_place_id', $workplace)
            ->whereDate('date', $start->toDateString())
            ->where('copied', WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->orderBy('activity')
            ->get();

        $groupedByActivity = [];

        foreach ($activities as $activity) {
            $records = WorkerRecord::where('work_place_id', $workplace)
                ->where('work_place_activity_id', $activity->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->with('worker')
                ->get()
                ->groupBy('worker_id');

            $pivotWorkerIds = $activity->temporaryWorkers()
                ->wherePivot('date', $start->toDateString())
                ->pluck('viki_workers.id');

            $workerIds = $records->keys()->merge($pivotWorkerIds)->unique();

            $monthKey = sprintf('%02d-%d', $month, $year);
            $hourRate = $this->getHourCostOnWorkPlaceActivityByDate($activity, $monthKey);
            $monthlyHours = $this->getActivityWorkingHoursForDate($activity, $monthKey);
            $workerCount = (int) ($activity->worker_count ?? 0);
            $maxBudget = ($activity->neto_salary + $activity->social_plus) * $workerCount;
            $maxHours = $monthlyHours * $workerCount;

            $activityData = [
                'activity_id' => $activity->id,
                'activity_name' => $activity->activity,
                'activity_salary' => $activity->neto_salary + $activity->social_plus,
                'hour_rate' => $hourRate,
                'workers' => [],
                'group_totals' => [
                    'used_budget' => 0,
                    'max_budget' => $maxBudget,
                    'used_hours' => 0,
                    'max_hours' => $maxHours,
                ],
            ];

            foreach ($workerIds as $workerId) {
                $worker = $records->has($workerId)
                    ? $records[$workerId]->first()->worker
                    : Worker::find($workerId);

                if (!$worker) {
                    continue;
                }

                $workerRecords = $records->get($workerId, collect());
                $hasWorkerRecords = $workerRecords->isNotEmpty();

                if (!$hasWorkerRecords && $worker->status !== Worker::WORKER_ACTIVE) {
                    continue;
                }

                $recordsByDay = $workerRecords->keyBy(fn ($record) => Carbon::parse($record->date)->day);

                $totalHours = 0;
                $dailyRecords = [];

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dailyRecord = $recordsByDay->get($day);
                    $dailyRecords[$day] = $dailyRecord ? $dailyRecord->hours : 0;
                    $totalHours += $dailyRecords[$day];
                }

                $calculatedPrice = $totalHours * $hourRate;
                $roundedHours = round($totalHours, 2);

                $activityData['workers'][] = [
                    'worker' => $worker,
                    'total_hours' => $roundedHours,
                    'calculated_price' => $calculatedPrice,
                    'daily_records' => $dailyRecords,
                ];

                $activityData['group_totals']['used_budget'] += $calculatedPrice;
                $activityData['group_totals']['used_hours'] += $roundedHours;
            }

            $activityData['group_totals']['used_budget'] = round($activityData['group_totals']['used_budget'], 2);
            $activityData['group_totals']['used_hours'] = round($activityData['group_totals']['used_hours'], 2);
            $activityData['group_totals']['max_budget'] = round($activityData['group_totals']['max_budget'], 2);
            $activityData['group_totals']['max_hours'] = round($activityData['group_totals']['max_hours'], 2);

            $groupedByActivity[] = $activityData;
        }

        $workplaceName = str_replace(' ', '_', $workplaceData->name);
        $filename = "monthly_presence_{$workplaceName}_{$year}_{$month}.xlsx";

        $export = new MonthlyPresenceExport($groupedByActivity, $workplaceData, $year, $month, $daysInMonth);

        return Excel::download($export, $filename);
    }

    private function getHourCostOnWorkPlaceActivityByDate($activity, $monthKey)
    {
        $activityCosts = json_decode($activity->activity_hour_cost_budget, true) ?? [];
        return isset($activityCosts[$monthKey]['hour_cost']) ? (float) $activityCosts[$monthKey]['hour_cost'] : 0;
    }

    private function getActivityWorkingHoursForDate($activity, $monthKey)
    {
        $activityCosts = json_decode($activity->activity_hour_cost_budget, true) ?? [];
        return isset($activityCosts[$monthKey]['working_hours']) ? (float) $activityCosts[$monthKey]['working_hours'] : 0;
    }
}
