<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use viki\Service\Models\Elequent\WorkPlaceActivity;
use viki\Service\Models\Elequent\HoursActivityByMonth;
use viki\Service\Models\Elequent\SpecialDay;

class FixActivityHours extends Command
{
    protected $signature = 'hours:fix-activities {month?} {year?}';
    protected $description = 'Fix configured hours for activities by parsing hours/day from activity names';

    public function handle()
    {
        $month = $this->argument('month') ?? 1;
        $year = $this->argument('year') ?? 2026;

        $this->info("Fixing configured hours for {$month}/{$year}...");

        // Calculate working days for this month
        $workingDays = $this->calculateWorkingDays($month, $year);
        $this->line("Working days in {$month}/{$year}: {$workingDays}");

        // Get all WORKING_STANDART activities for this month
        $monthDate = sprintf('%04d-%02d-01', $year, $month);
        $activities = WorkPlaceActivity::whereDate('date', $monthDate)
            ->where('type_working', WorkPlaceActivity::WORKING_STANDART)
            ->where('copied', WorkPlaceActivity::COPIED_ACTIVITY)
            ->get();

        $fixedCount = 0;
        $skippedCount = 0;

        foreach ($activities as $activity) {
            // Try to parse hours per day from activity name
            // Patterns: "3 ч", "3ч", "10ч", "8ч."
            if (preg_match('/(\d+)\s*ч/u', $activity->activity, $matches)) {
                $hoursPerDay = (int)$matches[1];
                $expectedHours = $workingDays * $hoursPerDay;

                $hoursRecord = $activity->hours()->where('date', $monthDate)->first();

                if (!$hoursRecord) {
                    $skippedCount++;
                    continue;
                }

                if ($hoursRecord->hours_for_person != $expectedHours) {
                    $oldHours = $hoursRecord->hours_for_person;
                    $hoursRecord->hours_for_person = $expectedHours;

                    if ($hoursRecord->save()) {
                        $fixedCount++;
                        $this->line(sprintf(
                            'Fixed %s (ID: %d, WP: %d): %s → %s hours (%d h/day)',
                            $activity->activity,
                            $activity->id,
                            $activity->work_place_id,
                            $oldHours,
                            $expectedHours,
                            $hoursPerDay
                        ));
                    }
                }
            } else {
                $skippedCount++;
            }
        }

        $this->newLine();
        $this->info("✅ Fixed: {$fixedCount} activities");
        $this->line("⏩ Skipped: {$skippedCount} activities (no hours pattern in name or no config)");

        return 0;
    }

    private function calculateWorkingDays(int $month, int $year): int
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        // Get special days (holidays)
        $specialDays = SpecialDay::where('date', 'like', sprintf('%04d-%02d-%%', $year, $month))
            ->where('type', SpecialDay::NO_WORKING)
            ->get()
            ->map(function ($day) {
                return (int)substr($day->date, strrpos($day->date, '-') + 1);
            })
            ->toArray();

        // Calculate weekends
        $weekends = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = \Carbon\Carbon::create($year, $month, $day);
            if ($date->isWeekend()) {
                $weekends[] = $day;
            }
        }

        // Combine special days and weekends, avoiding duplicates
        $nonWorkingDays = $weekends;
        foreach ($specialDays as $specialDay) {
            if (!in_array($specialDay, $nonWorkingDays)) {
                $nonWorkingDays[] = $specialDay;
            }
        }

        return $daysInMonth - count($nonWorkingDays);
    }
}
