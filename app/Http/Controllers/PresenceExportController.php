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
use viki\Service\Models\Elequent\WorkPlaceActivityMonthSnapshot;
use viki\Service\Models\Elequent\Vacation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PresenceExportController extends Controller
{
    public function exportMonthlyPresence(Request $request)
    {
        [
            $workplaceData,
            $year,
            $month,
            $daysInMonth,
            $groupedByActivity,
        ] = $this->prepareMonthlyPresenceData($request);

        $workplaceName = str_replace(" ", "_", $workplaceData->name);
        $filename = "monthly_presence_{$workplaceName}_{$year}_{$month}.xlsx";

        $export = new MonthlyPresenceExport(
            $groupedByActivity,
            $workplaceData,
            $year,
            $month,
            $daysInMonth,
            array_fill_keys($this->getAllNonWorkingDays($month, $year), true)
        );

        return Excel::download($export, $filename);
    }

    public function printMonthlyPresence(Request $request)
    {
        [
            $workplaceData,
            $year,
            $month,
            $daysInMonth,
            $groupedByActivity,
        ] = $this->prepareMonthlyPresenceData($request);

        $isLocked = DB::table("viki_monthly_presence_locks")
            ->where("work_place_id", $workplaceData->id)
            ->where("year", $year)
            ->where("month", $month)
            ->where("is_locked", true)
            ->exists();

        if (!$isLocked) {
            abort(403, "Месецът трябва да бъде заключен преди печат.");
        }

        return view("prints.monthly-presence", [
            "workplaceData" => $workplaceData,
            "year" => $year,
            "month" => $month,
            "daysInMonth" => $daysInMonth,
            "groupedByActivity" => $groupedByActivity,
            "specialDayMap" => $this->getSpecialDayMap($month, $year),
            "nonWorkingDaysMap" => array_fill_keys(
                $this->getAllNonWorkingDays($month, $year),
                true
            ),
            "monthName" => $this->getMonthName($month, $year),
        ]);
    }

    private function prepareMonthlyPresenceData(Request $request): array
    {
        $workplace = (int) $request->get("workplace");
        $year = (int) ($request->get("year") ?: Carbon::now()->year);
        $month = (int) ($request->get("month") ?: Carbon::now()->month);

        if ($month < 1 || $month > 12) {
            abort(422, "Невалиден месец.");
        }

        $workplaceData = WorkPlace::where(
            "status",
            WorkPlace::WORK_PLACE_ACTIVE
        )
            ->with("region", "client")
            ->find($workplace);

        if (!$workplaceData) {
            abort(404, "Workplace not found");
        }

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;

        $groupedByActivity = $this->buildGroupedByActivity(
            $workplace,
            $year,
            $month,
            $start,
            $end,
            $daysInMonth
        );

        return [
            $workplaceData,
            $year,
            $month,
            $daysInMonth,
            $groupedByActivity,
        ];
    }

    private function buildGroupedByActivity(
        int $workplace,
        int $year,
        int $month,
        Carbon $start,
        Carbon $end,
        int $daysInMonth
    ): array {
        $activities = WorkPlaceActivity::where("work_place_id", $workplace)
            ->whereNull("date")
            ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->orderBy("activity")
            ->get();
        $vacationData = $this->loadVacationDataForActivities(
            $activities,
            $workplace,
            $start,
            $end
        );

        $groupedByActivity = [];
        $monthKey = sprintf("%02d-%d", $month, $year);
        $normalizedDate = sprintf("%04d-%02d-01", $year, $month);

        // Load snapshots for this month (only populated for locked months).
        $snapshots = WorkPlaceActivityMonthSnapshot::getForMonth(
            $workplace,
            $normalizedDate
        );

        foreach ($activities as $activity) {
            $snapshot = $snapshots->get($activity->id);
            $effectiveSalary = $snapshot
                ? (float) $snapshot->neto_salary
                : (float) $activity->neto_salary;
            $effectiveName = $snapshot
                ? $snapshot->activity
                : $activity->activity;
            $effectiveCount = $snapshot
                ? (int) $snapshot->worker_count
                : (int) ($activity->worker_count ?? 0);
            $snapshotHoursPerDay = $snapshot ? $snapshot->hours_per_day : null;

            $records = WorkerRecord::where("work_place_id", $workplace)
                ->where("work_place_activity_id", $activity->id)
                ->whereBetween("date", [
                    $start->toDateString(),
                    $end->toDateString(),
                ])
                ->with("worker")
                ->get()
                ->groupBy("worker_id");

            $pivotWorkerIds = $activity
                ->temporaryWorkers()
                ->wherePivot("date", $start->toDateString())
                ->pluck("viki_workers.id");

            $workerIds = $records->keys()->merge($pivotWorkerIds)->unique();

            $hourRate = $this->getHourCostOnWorkPlaceActivityByDate(
                $activity,
                $monthKey,
                $effectiveSalary,
                $snapshotHoursPerDay
            );
            $monthlyHours = $this->getActivityWorkingHoursForDate(
                $activity,
                $monthKey,
                $snapshotHoursPerDay
            );
            $maxBudget = $effectiveSalary * $effectiveCount;
            $maxHours = $monthlyHours * $effectiveCount;

            $activityData = [
                "activity_id" => $activity->id,
                "activity_name" => $effectiveName,
                "activity_salary" => $effectiveSalary,
                "hour_rate" => $hourRate,
                "workers" => [],
                "group_totals" => [
                    "used_budget" => 0,
                    "max_budget" => $maxBudget,
                    "used_hours" => 0,
                    "max_hours" => $maxHours,
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

                if (
                    !$hasWorkerRecords &&
                    $worker->status !== Worker::WORKER_ACTIVE
                ) {
                    continue;
                }

                $recordsByDay = $workerRecords->keyBy(
                    fn($record) => Carbon::parse($record->date)->day
                );

                $totalHours = 0;
                $dailyRecords = [];
                $dailyLeaveInfo = [];

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dailyRecord = $recordsByDay->get($day);
                    $dailyRecords[$day] = $dailyRecord
                        ? $dailyRecord->hours
                        : 0;
                    $dailyLeaveInfo[$day] =
                        $vacationData[$workerId][$day] ?? null;
                    $totalHours += $dailyRecords[$day];
                }

                $calculatedPrice = $totalHours * $hourRate;
                $roundedHours = round($totalHours, 2);

                $activityData["workers"][] = [
                    "worker" => $worker,
                    "total_hours" => $roundedHours,
                    "calculated_price" => $calculatedPrice,
                    "daily_records" => $dailyRecords,
                    "daily_leave_info" => $dailyLeaveInfo,
                ];

                $activityData["group_totals"][
                    "used_budget"
                ] += $calculatedPrice;
                $activityData["group_totals"]["used_hours"] += $roundedHours;
            }

            $activityData["group_totals"]["used_budget"] = round(
                $activityData["group_totals"]["used_budget"],
                2
            );
            $activityData["group_totals"]["used_hours"] = round(
                $activityData["group_totals"]["used_hours"],
                2
            );
            $activityData["group_totals"]["max_budget"] = round(
                $activityData["group_totals"]["max_budget"],
                2
            );
            $activityData["group_totals"]["max_hours"] = round(
                $activityData["group_totals"]["max_hours"],
                2
            );

            $groupedByActivity[] = $activityData;
        }

        return $groupedByActivity;
    }

    private function loadVacationDataForActivities(
        $activities,
        int $workplace,
        Carbon $start,
        Carbon $end
    ): array {
        $workerIds = collect();

        foreach ($activities as $activity) {
            $pivotWorkerIds = $activity
                ->temporaryWorkers()
                ->wherePivot("date", $start->toDateString())
                ->pluck("viki_workers.id");

            $recordWorkerIds = WorkerRecord::where("work_place_id", $workplace)
                ->where("work_place_activity_id", $activity->id)
                ->whereBetween("date", [
                    $start->toDateString(),
                    $end->toDateString(),
                ])
                ->distinct()
                ->pluck("worker_id");

            $workerIds = $workerIds
                ->merge($pivotWorkerIds)
                ->merge($recordWorkerIds);
        }

        $workerIds = $workerIds->unique()->values();

        if ($workerIds->isEmpty()) {
            return [];
        }

        $vacations = Vacation::whereIn("worker_id", $workerIds->all())
            ->where("status", 1)
            ->where(function ($query) use ($start, $end) {
                $query
                    ->whereBetween("start_date", [
                        $start->toDateString(),
                        $end->toDateString(),
                    ])
                    ->orWhereBetween("end_date", [
                        $start->toDateString(),
                        $end->toDateString(),
                    ])
                    ->orWhere(function ($nested) use ($start, $end) {
                        $nested
                            ->where("start_date", "<=", $start->toDateString())
                            ->where("end_date", ">=", $end->toDateString());
                    });
            })
            ->get();

        $vacationData = [];

        foreach ($vacations as $vacation) {
            $this->processVacationDays($vacation, $start, $end, $vacationData);
        }

        return $vacationData;
    }

    private function processVacationDays(
        Vacation $vacation,
        Carbon $startDate,
        Carbon $endDate,
        array &$vacationData
    ): void {
        $workerId = (int) $vacation->worker_id;
        $vacStart = Carbon::parse($vacation->start_date);
        $vacEnd = Carbon::parse($vacation->end_date);
        $typeInfo = $this->getVacationTypeInfo((int) $vacation->type);
        $current = max($vacStart, $startDate)->copy();
        $end = min($vacEnd, $endDate)->copy();

        while ($current <= $end) {
            $vacationData[$workerId][$current->day] = [
                "type" => (int) $vacation->type,
                "short" => $typeInfo["short"],
                "label" => $typeInfo["label"],
                "class" => $typeInfo["class"],
                "comment" => $vacation->comment,
            ];
            $current->addDay();
        }
    }

    private function getVacationTypeInfo(int $type): array
    {
        $types = [
            Vacation::PAYD_VACATION => [
                "label" => "Платена отпуска",
                "short" => "ПО",
                "class" => "leave-paid",
            ],
            Vacation::NOT_PAYD_VACATION => [
                "label" => "Неплатена отпуска",
                "short" => "НО",
                "class" => "leave-unpaid",
            ],
            Vacation::HOSPITAL_SHEET => [
                "label" => "Болничен",
                "short" => "БЛ",
                "class" => "leave-hospital",
            ],
        ];

        return $types[$type] ?? [
            "label" => "Неизвестен тип",
            "short" => "?",
            "class" => "",
        ];
    }

    private function getSpecialDayMap(int $month, int $year): array
    {
        $specialDays = SpecialDay::where(
            "date",
            "like",
            sprintf("%04d-%02d-%%", $year, $month)
        )->get();

        $map = [];
        foreach ($specialDays as $specialDay) {
            $day = (int) Carbon::parse($specialDay->date)->day;
            $map[$day] = [
                "label" => $specialDay->comment ?? "Празничен ден",
                "type" => (int) $specialDay->type,
            ];
        }

        return $map;
    }

    private function getMonthName(int $month, int $year): string
    {
        $monthNames = [
            1 => "Януари",
            2 => "Февруари",
            3 => "Март",
            4 => "Април",
            5 => "Май",
            6 => "Юни",
            7 => "Юли",
            8 => "Август",
            9 => "Септември",
            10 => "Октомври",
            11 => "Ноември",
            12 => "Декември",
        ];

        return ($monthNames[$month] ?? (string) $month) . " " . $year;
    }

    private function getHourCostOnWorkPlaceActivityByDate(
        $activity,
        $monthKey,
        ?float $overrideSalary = null,
        ?float $overrideHoursPerDay = null
    ) {
        $workingHours = $this->getActivityWorkingHoursForDate(
            $activity,
            $monthKey,
            $overrideHoursPerDay
        );
        if ($workingHours <= 0) {
            return 0;
        }

        $salary = $overrideSalary ?? (float) $activity->neto_salary;

        return $salary / $workingHours;
    }

    private function getActivityWorkingHoursForDate(
        $activity,
        $monthKey,
        ?float $overrideHoursPerDay = null
    ) {
        [$month, $year] = array_map("intval", explode("-", $monthKey));
        $normalizedDate = sprintf("%04d-%02d-01", $year, $month);

        $hoursConfig = HoursActivityByMonth::query()
            ->where("work_place_activity_id", $activity->id)
            ->where("date", $normalizedDate)
            ->first();

        $hoursPerDay = $overrideHoursPerDay
            ? (int) $overrideHoursPerDay
            : (int) WorkPlaceActivityHoursPerDay::findHoursPerDayPerActivity(
                $activity->id
            );
        if (
            $hoursPerDay <= 0 &&
            preg_match("/(\d+)\s*ч/u", (string) $activity->activity, $matches)
        ) {
            $hoursPerDay = (int) ($matches[1] ?? 0);
        }
        if ($hoursPerDay <= 0) {
            $hoursPerDay = 8;
        }

        $calculatedHours =
            (cal_days_in_month(CAL_GREGORIAN, $month, $year) -
                count($this->getAllNonWorkingDays($month, $year))) *
            $hoursPerDay;

        if ($hoursConfig) {
            if (
                (int) $activity->type_working ===
                WorkPlaceActivity::WORKING_BY_HOURS
            ) {
                return (float) $hoursConfig->hours_for_person;
            }
        }

        if (
            (int) $activity->type_working ===
            WorkPlaceActivity::WORKING_STANDART
        ) {
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
            ->where("date", "like", sprintf("%d-%02d-%%", $year, $month))
            ->get()
            ->map(function (SpecialDay $day) {
                return (int) substr($day->date, strrpos($day->date, "-") + 1);
            })
            ->all();
    }

    private function getWeekendDays(int $month, int $year): array
    {
        $weekendDays = [];

        foreach (
            range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year))
            as $day
        ) {
            if (
                date(
                    "N",
                    strtotime(sprintf("%d-%02d-%02d", $year, $month, $day))
                ) >= 6
            ) {
                $weekendDays[] = $day;
            }
        }

        return $weekendDays;
    }
}
