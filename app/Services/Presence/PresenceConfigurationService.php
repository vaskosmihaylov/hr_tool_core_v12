<?php

namespace App\Services\Presence;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use viki\Service\Models\Elequent\HoursActivityByMonth;
use viki\Service\Models\Elequent\SpecialDay;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\WorkPlaceActivityHoursPerDay;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkerRecord;

class PresenceConfigurationService
{
    /**
     * Monthly snapshot generation is disabled.
     * Presence now works directly with base activities (date = null, copied = 0).
     */
    public static function ensureMonthlyActivities(int $workplaceId, int $year, int $month): void
    {
        return;
    }

    private static function ensureMonthlyActivityForBase(
        WorkPlace $workplace,
        WorkPlaceActivity $baseActivity,
        int $year,
        int $month,
        string $monthString
    ): void {
        $normalizedDate = sprintf('%d-%s-01', $year, $monthString);

        // Look for monthly snapshot with copied=1
        $existingMonthly = WorkPlaceActivity::query()
            ->where('work_place_id', $workplace->id)
            ->where('copied', WorkPlaceActivity::COPIED_ACTIVITY)
            ->whereDate('date', $normalizedDate)
            ->where('activity', $baseActivity->activity)
            ->where('type_working', $baseActivity->type_working)
            ->first();

        if ($existingMonthly) {
            $existingMonthly->update([
                'neto_salary' => $baseActivity->neto_salary,
                'worker_count' => $baseActivity->worker_count,
            ]);
            return;
        }

        DB::transaction(function () use ($workplace, $baseActivity, $year, $month, $monthString) {
            $monthlyActivity = self::createMonthlyActivityFromBase($baseActivity, $year, $monthString);
            self::attachExistingWorkersToMonthlyActivity($workplace, $baseActivity, $monthlyActivity, $year, $month);
        });
    }

    /**
     * Return the hours configured for the supplied activities keyed by activity id.
     */
    public static function getHoursForActivities(iterable $activities, string $normalizedDate): array
    {
        $hours = [];

        foreach ($activities as $activity) {
            $record = HoursActivityByMonth::where('work_place_activity_id', $activity->id)
                ->where('date', $normalizedDate)
                ->first();

            if ($record) {
                $hours[$activity->id] = $record->hours_for_person;
            }
        }

        return $hours;
    }

    /**
     * Validate that adding or updating an activity keeps the workplace within budget.
     */
    public static function checkWorkplaceBudget(array $payload, int $workplaceId, string $normalizedDate): bool
    {
        $date = Carbon::parse($normalizedDate);
        $month = (int) $date->format('m');
        $year = (int) $date->format('Y');

        $existingTotal = WorkPlaceActivity::where('work_place_id', $workplaceId)
            ->whereNull('date')
            ->where('copied', WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->get()
            ->sum(function (WorkPlaceActivity $activity) {
                return $activity->neto_salary * $activity->worker_count;
            });

        $neto = (float) ($payload['neto_salary'] ?? 0);
        $workerCount = max(1, (int) ($payload['worker_count'] ?? 1));

        $candidateTotal = $existingTotal + ($neto * $workerCount);

        $workplace = WorkPlace::find($workplaceId);
        if (!$workplace) {
            return false;
        }

        $budget = $workplace->getBudgetByDate(sprintf('%02d-%d', $month, $year));

        if ($budget === null) {
            return true;
        }

        return $candidateTotal <= $budget;
    }

    /**
     * Attach a worker to a workplace activity for a given month, mirroring the legacy behaviour.
     * A worker CAN be assigned to multiple activities within the same workplace and month.
     */
    public static function addWorkerToActivity(int $workplaceId, int $activityId, int $workerId, string $monthYear): void
    {
        $date = Carbon::createFromFormat('m-Y', $monthYear);
        if (!$date || $date->format('m-Y') !== $monthYear) {
            throw new RuntimeException('Невалиден формат на месеца.');
        }

        $workplace = WorkPlace::findOrFail($workplaceId);
        $activity = WorkPlaceActivity::findOrFail($activityId);

        if ($activity->work_place_id !== $workplaceId) {
            throw new RuntimeException('Избраната дейност не принадлежи на този обект.');
        }

        // Always attach workers to the base activity.
        // If a monthly snapshot activity id is provided, remap it to its base counterpart.
        if ($activity->date !== null || (int) $activity->copied === WorkPlaceActivity::COPIED_ACTIVITY) {
            $baseActivity = WorkPlaceActivity::query()
                ->where('work_place_id', $workplaceId)
                ->whereNull('date')
                ->where('copied', WorkPlaceActivity::NOT_COPIED_ACTIVITY)
                ->where('activity', $activity->activity)
                ->where('type_working', $activity->type_working)
                ->orderByDesc('id')
                ->first();

            if (!$baseActivity) {
                throw new RuntimeException('Не е намерена базова дейност за избраната месечна дейност.');
            }

            $activity = $baseActivity;
        }

        $worker = Worker::findOrFail($workerId);

        $normalizedDate = $date->copy()->startOfMonth()->toDateString();

        DB::transaction(function () use ($workplace, $activity, $worker, $normalizedDate, $date) {
            // Check if worker is already attached to THIS SPECIFIC activity for this month
            // This is the only check that should fail - we want to prevent duplicate activity assignments
            $activityPivotExists = $activity->temporaryWorkers()
                ->wherePivot('worker_id', $worker->id)
                ->wherePivot('date', $normalizedDate)
                ->exists();

            if ($activityPivotExists) {
                throw new RuntimeException('Този работник вече е добавен към избраната дейност.');
            }

            // Check workplace-level pivot - but DON'T fail if exists
            // A worker CAN be in multiple activities within the same workplace/month
            // We only need one entry in the workplace pivot per worker/month combination
            $workplacePivotExists = $workplace->temporaryWorkers()
                ->wherePivot('worker_id', $worker->id)
                ->wherePivot('date', $normalizedDate)
                ->exists();

            // Only add to workplace pivot if not already there
            if (!$workplacePivotExists) {
                $workplace->temporaryWorkers()->attach($worker->id, ['date' => $normalizedDate]);
            }

            // Always add to activity pivot (we already checked for duplicates above)
            $activity->temporaryWorkers()->attach($worker->id, ['date' => $normalizedDate]);

            if ($activity->type_working === WorkPlaceActivity::WORKING_STANDART) {
                $startDate = $worker->start_date ? Carbon::parse($worker->start_date) : $date->copy();
                $startOfMonth = $date->copy()->startOfMonth();
                $endOfMonth = $date->copy()->endOfMonth();

                if ($startDate->lt($startOfMonth)) {
                    $startDate = $startOfMonth;
                }

                if ($startDate->lte($endOfMonth)) {
                    self::insertStandardWorkerRecords(
                        $worker,
                        $activity,
                        $activity,
                        $workplace,
                        $startDate,
                        $endOfMonth
                    );
                }
            }
        });
    }

    private static function createMonthlyActivityFromBase(WorkPlaceActivity $baseActivity, int $year, string $monthString): WorkPlaceActivity
    {
        $normalizedDate = sprintf('%d-%s-01', $year, $monthString);

        $attributes = [
            'activity' => $baseActivity->activity,
            'copied' => WorkPlaceActivity::COPIED_ACTIVITY,
            'type_working' => $baseActivity->type_working,
            'neto_salary' => $baseActivity->neto_salary,
            'worker_count' => $baseActivity->worker_count,
            'date' => $normalizedDate,
            'work_place_id' => $baseActivity->work_place_id,
            'created_by' => Auth::id(),
        ];

        $monthlyActivity = WorkPlaceActivity::createCopied($attributes);

        if ($baseActivity->type_working === WorkPlaceActivity::WORKING_STANDART) {
            $hoursPerDay = WorkPlaceActivityHoursPerDay::findHoursPerDayPerActivity($baseActivity->id);
            if (empty($hoursPerDay)) {
                $hoursPerDay = 8;
            }

            WorkPlaceActivityHoursPerDay::create($hoursPerDay, $monthlyActivity->id);

            $hoursForMonth = (cal_days_in_month(CAL_GREGORIAN, (int) $monthString, $year) - count(self::getAllNonWorkingDays((int) $monthString, $year))) * (int) $hoursPerDay;

            HoursActivityByMonth::updateOrCreate(
                [
                    'work_place_activity_id' => $monthlyActivity->id,
                    'date' => $normalizedDate,
                ],
                [
                    'hours_for_person' => $hoursForMonth,
                    'created_by' => Auth::id(),
                ]
            );
        } elseif ($baseActivity->type_working === WorkPlaceActivity::WORKING_BY_HOURS) {
            // For сумарно (hourly) activities, try to copy hours from previous month
            self::copyHoursFromPreviousMonth($baseActivity, $monthlyActivity, $year, (int) $monthString, $normalizedDate);
        }

        return $monthlyActivity;
    }

    private static function attachExistingWorkersToMonthlyActivity(
        WorkPlace $workplace,
        WorkPlaceActivity $baseActivity,
        WorkPlaceActivity $monthlyActivity,
        int $year,
        int $month
    ): void {
        // First, try to find workers assigned directly to the base activity
        $workers = Worker::where('status', Worker::USER_ACTIVE)
            ->where('work_place_activity_id', $baseActivity->id)
            ->get();

        // If no workers found on base activity, try to find workers from previous month's activity
        if ($workers->isEmpty()) {
            $workers = self::getWorkersFromPreviousMonth($workplace, $baseActivity, $year, $month);
        }

        if ($workers->isEmpty()) {
            return;
        }

        $firstDayOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $lastDayOfMonth = (clone $firstDayOfMonth)->endOfMonth();
        $normalizedDate = $firstDayOfMonth->toDateString();

        foreach ($workers as $worker) {
            $startDate = $worker->start_date ? Carbon::parse($worker->start_date) : $firstDayOfMonth->copy();

            if ($startDate->lt($firstDayOfMonth)) {
                $startDate = $firstDayOfMonth->copy();
            }

            if ($startDate->gt($lastDayOfMonth)) {
                continue;
            }

            // Check if worker is already in workplace pivot for this specific date
            $workplacePivotExists = $workplace->temporaryWorkers()
                ->wherePivot('worker_id', $worker->id)
                ->wherePivot('date', $normalizedDate)
                ->exists();

            // Only attach if not already present for this date
            if (!$workplacePivotExists) {
                $workplace->temporaryWorkers()->attach($worker->id, ['date' => $normalizedDate]);
            }

            // Check if worker is already in activity pivot for this specific date
            $activityPivotExists = $monthlyActivity->temporaryWorkers()
                ->wherePivot('worker_id', $worker->id)
                ->wherePivot('date', $normalizedDate)
                ->exists();

            // Only attach if not already present for this date
            if (!$activityPivotExists) {
                $monthlyActivity->temporaryWorkers()->attach($worker->id, ['date' => $normalizedDate]);
            }

            if ($baseActivity->type_working === WorkPlaceActivity::WORKING_STANDART) {
                self::insertStandardWorkerRecords($worker, $baseActivity, $monthlyActivity, $workplace, $startDate, $lastDayOfMonth);
            }
        }
    }


    /**
     * Get workers from the previous month's activity with the same name and type.
     * This handles cases where workers are assigned to monthly snapshots rather than base activities.
     */
    private static function getWorkersFromPreviousMonth(
        WorkPlace $workplace,
        WorkPlaceActivity $baseActivity,
        int $year,
        int $month
    ): \Illuminate\Support\Collection {
        // Calculate previous month
        $previousDate = Carbon::create($year, $month, 1)->subMonth();
        $previousNormalizedDate = $previousDate->startOfMonth()->toDateString();

        // Find the previous month's activity with the same name and type
        $previousMonthActivity = WorkPlaceActivity::where('work_place_id', $workplace->id)
            ->where('copied', WorkPlaceActivity::COPIED_ACTIVITY)
            ->whereDate('date', $previousNormalizedDate)
            ->where('activity', $baseActivity->activity)
            ->where('type_working', $baseActivity->type_working)
            ->first();

        if (!$previousMonthActivity) {
            return collect();
        }

        // Get active workers who were assigned to the previous month's activity via pivot table
        $workerIds = $previousMonthActivity->temporaryWorkers()
            ->wherePivot('date', $previousNormalizedDate)
            ->pluck('viki_workers.id');

        if ($workerIds->isEmpty()) {
            return collect();
        }

        // Return only active workers
        return Worker::whereIn('id', $workerIds)
            ->where('status', Worker::USER_ACTIVE)
            ->get();
    }

    private static function insertStandardWorkerRecords(
        Worker $worker,
        WorkPlaceActivity $baseActivity,
        WorkPlaceActivity $monthlyActivity,
        WorkPlace $workplace,
        Carbon $startDate,
        Carbon $lastDayOfMonth
    ): void {
        $hoursPerDay = WorkPlaceActivityHoursPerDay::findHoursPerDayPerActivity($baseActivity->id);
        if (empty($hoursPerDay)) {
            $hoursPerDay = 8;
        }

        // Get special days for this month to exclude holidays
        $month = $startDate->month;
        $year = $startDate->year;
        $specialDayNumbers = self::getSpecialDays($month, $year);

        $current = $startDate->copy();
        while ($current->lte($lastDayOfMonth)) {
            $dayNumber = (int) $current->day;
            $isSpecialDay = in_array($dayNumber, $specialDayNumbers, true);

            // Only insert records if it's not a weekend AND not a special day/holiday
            if (!self::isWeekend($current) && !$isSpecialDay) {
                WorkerRecord::updateOrCreate(
                    [
                        'work_place_activity_id' => $monthlyActivity->id,
                        'worker_id' => $worker->id,
                        'work_place_id' => $workplace->id,
                        'date' => $current->toDateString(),
                    ],
                    [
                        'hours' => $hoursPerDay,
                        'day_count' => 0,
                        'status' => WorkerRecord::WORKER_RECORD_APPROVED,
                        'start_date' => now()->toDateString(),
                        'end_date' => now()->toDateString(),
                        'creator_id' => Auth::id(),
                    ]
                );
            }

            $current->addDay();
        }
    }

    private static function getAllNonWorkingDays(int $month, int $year): array
    {
        $specialDays = self::getSpecialDays($month, $year);
        $weekendDays = self::getWeekendDays($month, $year);

        foreach ($specialDays as $specialDay) {
            if (!in_array($specialDay, $weekendDays, true)) {
                $weekendDays[] = $specialDay;
            }
        }

        return $weekendDays;
    }

    private static function getSpecialDays(int $month, int $year): array
    {
        return SpecialDay::where('date', 'like', sprintf('%d-%02d-%%', $year, $month))
            ->get()
            ->map(function (SpecialDay $day) {
                return (int) substr($day->date, strrpos($day->date, '-') + 1);
            })
            ->all();
    }

    private static function getWeekendDays(int $month, int $year): array
    {
        $weekendDays = [];

        foreach (range(1, cal_days_in_month(CAL_GREGORIAN, $month, $year)) as $day) {
            if (date('N', strtotime(sprintf('%d-%02d-%02d', $year, $month, $day))) >= 6) {
                $weekendDays[] = $day;
            }
        }

        return $weekendDays;
    }

    private static function isWeekend(Carbon $date): bool
    {
        return $date->isWeekend();
    }

    /**
     * Copy hours configuration from previous month for сумарно activities.
     * If previous month has configured hours, use those. Otherwise, do nothing (will need manual configuration).
     */
    private static function copyHoursFromPreviousMonth(
        WorkPlaceActivity $baseActivity,
        WorkPlaceActivity $monthlyActivity,
        int $year,
        int $month,
        string $normalizedDate
    ): void {
        // Calculate previous month
        $previousDate = Carbon::create($year, $month, 1)->subMonth();
        $previousYear = $previousDate->year;
        $previousMonth = $previousDate->month;
        $previousNormalizedDate = sprintf('%d-%02d-01', $previousYear, $previousMonth);

        // Find the activity from previous month with same name
        $previousMonthActivity = WorkPlaceActivity::where('work_place_id', $baseActivity->work_place_id)
            ->where('copied', WorkPlaceActivity::COPIED_ACTIVITY)
            ->whereDate('date', $previousNormalizedDate)
            ->where('activity', $baseActivity->activity)
            ->where('type_working', WorkPlaceActivity::WORKING_BY_HOURS)
            ->first();

        if (!$previousMonthActivity) {
            // No previous month activity found, skip copying
            return;
        }

        // Check if previous month has configured hours
        $previousHoursConfig = HoursActivityByMonth::where('work_place_activity_id', $previousMonthActivity->id)
            ->where('date', $previousNormalizedDate)
            ->first();

        if (!$previousHoursConfig || !$previousHoursConfig->hours_for_person) {
            // No hours configured in previous month, skip copying
            return;
        }

        // Copy the hours configuration to this month
        HoursActivityByMonth::updateOrCreate(
            [
                'work_place_activity_id' => $monthlyActivity->id,
                'date' => $normalizedDate,
            ],
            [
                'hours_for_person' => $previousHoursConfig->hours_for_person,
                'created_by' => Auth::id(),
            ]
        );
    }
}
