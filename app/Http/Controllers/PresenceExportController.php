<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\MonthlyPresenceExport;
use Maatwebsite\Excel\Facades\Excel;
use viki\Service\Models\Elequent\HoursActivityByMonth;
use viki\Service\Models\Elequent\SpecialDay;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay;
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

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;

        // Use only base activities for monthly presence exports.
        $activities = WorkPlaceActivity::where('work_place_id', $workplace)
            ->whereNull('date')
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
            $maxBudget = $activity->neto_salary * $workerCount;
            $maxHours = $monthlyHours * $workerCount;

            $activityData = [
                'activity_id' => $activity->id,
                'activity_name' => $activity->activity,
                'activity_salary' => $activity->neto_salary,
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
        $workingHours = $this->getActivityWorkingHoursForDate($activity, $monthKey);
        if ($workingHours <= 0) {
            return 0;
        }

        return (float) $activity->neto_salary / $workingHours;
    }

    private function getActivityWorkingHoursForDate($activity, $monthKey)
    {
        [$month, $year] = array_map('intval', explode('-', $monthKey));
        $normalizedDate = sprintf('%04d-%02d-01', $year, $month);

        $hoursConfig = HoursActivityByMonth::query()
            ->where('work_place_activity_id', $activity->id)
            ->where('date', $normalizedDate)
            ->first();

        $hoursPerDay = (int) WorkPlaceActivityHoursPerDay::findHoursPerDayPerActivity($activity->id);
        if ($hoursPerDay <= 0 && preg_match('/(\d+)\s*ч/u', (string) $activity->activity, $matches)) {
            $hoursPerDay = (int) ($matches[1] ?? 0);
        }
        if ($hoursPerDay <= 0) {
            $hoursPerDay = 8;
        }

        $calculatedHours = (cal_days_in_month(CAL_GREGORIAN, $month, $year) - count($this->getAllNonWorkingDays($month, $year))) * $hoursPerDay;

        if ($hoursConfig) {
            if ((int) $activity->type_working === WorkPlaceActivity::WORKING_BY_HOURS) {
                return (float) $hoursConfig->hours_for_person;
            }
        }

        if ((int) $activity->type_working === WorkPlaceActivity::WORKING_STANDART) {
            return (float) $calculatedHours;
        }

        return (float) $calculatedHours;
    }

    private function getAllNonWorkingDays(int $month, int $year): array
    {
        $specialDays = $this->getSpecialDays($month, $year);
        $weekendDays = $this->getWeekendDays($month, $year);

        foreach ($specialDays as $specialDay) {
            if (!in_array($specialDay, $weekendDays, true)) {
                $weekendDays[] = $specialDay;
            }
        }

        return $weekendDays;
    }

    private function getSpecialDays(int $month, int $year): array
    {
        return SpecialDay::query()
            ->where('date', 'like', sprintf('%d-%02d-%%', $year, $month))
            ->get()
            ->map(function (SpecialDay $day) {
                return (int) substr($day->date, strrpos($day->date, '-') + 1);
            })
            ->all();
    }

    private function getWeekendDays(int $month, int $year): array
    {
        $weekendDays = [];

        foreach (range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year)) as $day) {
            if (date('N', strtotime(sprintf('%d-%02d-%02d', $year, $month, $day))) >= 6) {
                $weekendDays[] = $day;
            }
        }

        return $weekendDays;
    }
}
