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
     * Ensure that a monthly snapshot of workplace activities exists for the given month.
     */
    public static function ensureMonthlyActivities(int $workplaceId, int $year, int $month): void
    {
        $monthString = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        if (WorkPlaceActivity::checkIfActivitiesAreCopied($workplaceId, $year, $monthString)) {
            return;
        }

        $workplace = WorkPlace::findOrFail($workplaceId);
        $baseActivities = WorkPlaceActivity::where('work_place_id', $workplaceId)
            ->whereNull('date')
            ->get();

        foreach ($baseActivities as $baseActivity) {
            DB::transaction(function () use ($baseActivity, $workplace, $year, $month, $monthString) {
                $monthlyActivity = self::createMonthlyActivityFromBase($baseActivity, $year, $monthString);
                self::attachExistingWorkersToMonthlyActivity($workplace, $baseActivity, $monthlyActivity, $year, $month);
            });
        }
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
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get()
            ->sum(function (WorkPlaceActivity $activity) {
                return ($activity->neto_salary + $activity->social_plus) * $activity->worker_count;
            });

        $social = (float) ($payload['social_plus'] ?? 0);
        $neto = (float) ($payload['neto_salary'] ?? 0);
        $workerCount = max(1, (int) ($payload['worker_count'] ?? 1));

        $candidateTotal = $existingTotal + (($neto + $social) * $workerCount);

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

        $worker = Worker::findOrFail($workerId);

        $normalizedDate = $date->copy()->startOfMonth()->toDateString();

        DB::transaction(function () use ($workplace, $activity, $worker, $normalizedDate, $date) {
            // Prevent duplicate attachments on the workplace-level pivot.
            $workplacePivotExists = $workplace->temporaryWorkers()
                ->wherePivot('worker_id', $worker->id)
                ->wherePivot('date', $normalizedDate)
                ->exists();

            if ($workplacePivotExists) {
                throw new RuntimeException('Този работник вече е добавен към обекта за избрания месец.');
            }

            $activityPivotExists = $activity->temporaryWorkers()
                ->wherePivot('worker_id', $worker->id)
                ->wherePivot('date', $normalizedDate)
                ->exists();

            if ($activityPivotExists) {
                throw new RuntimeException('Този работник вече е добавен към избраната дейност.');
            }

            $workplace->temporaryWorkers()->attach($worker->id, ['date' => $normalizedDate]);
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
            'social_plus' => $baseActivity->social_plus,
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
        $workers = Worker::where('status', Worker::USER_ACTIVE)
            ->where('work_place_activity_id', $baseActivity->id)
            ->get();

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

            $workplace->temporaryWorkers()->syncWithoutDetaching([
                $worker->id => ['date' => $normalizedDate],
            ]);

            $monthlyActivity->temporaryWorkers()->syncWithoutDetaching([
                $worker->id => ['date' => $normalizedDate],
            ]);

            if ($baseActivity->type_working === WorkPlaceActivity::WORKING_STANDART) {
                self::insertStandardWorkerRecords($worker, $baseActivity, $monthlyActivity, $workplace, $startDate, $lastDayOfMonth);
            }
        }
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

        $current = $startDate->copy();
        while ($current->lte($lastDayOfMonth)) {
            if (!self::isWeekend($current)) {
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
}
