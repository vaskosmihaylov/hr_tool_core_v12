<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use viki\Service\Models\Elequent\SpecialDay;

class ImportHolidays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holidays:import {year?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import holidays from the API for the specified year (defaults to current year)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->argument('year') ?? date('Y');

        $this->info("Importing holidays for year {$year}...");

        try {
            $result = SpecialDay::insertHolidays($year);

            $this->info("✅ {$result}");

            // Count imported holidays for this year
            $count = SpecialDay::where('date', 'LIKE', "{$year}-%")
                ->where('type', SpecialDay::NO_WORKING)
                ->count();

            $this->line("Total holidays in database for {$year}: {$count}");

            // Recalculate configured hours for affected months
            $this->recalculateConfiguredHours($year);

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to import holidays: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Recalculate configured hours for all WORKING_STANDART activities in the affected year
     * after holidays have been imported. This ensures hourly rates remain accurate.
     *
     * Instead of blindly recalculating as "working_days × 8", this method scales
     * the configured hours proportionally based on the change in working days.
     * This preserves activities that work fewer hours per day (e.g., 3 hours/day).
     */
    private function recalculateConfiguredHours(int $year): void
    {
        $this->info("Recalculating configured hours for {$year}...");

        $updatedCount = 0;

        // Process each month in the year
        for ($month = 1; $month <= 12; $month++) {
            $monthDate = sprintf('%04d-%02d-01', $year, $month);
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            // Calculate current working days (with holidays)
            $specialDays = SpecialDay::where('date', 'like', sprintf('%04d-%02d-%%', $year, $month))
                ->where('type', SpecialDay::NO_WORKING)
                ->get()
                ->map(function ($day) {
                    return (int)substr($day->date, strrpos($day->date, '-') + 1);
                })
                ->toArray();

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

            $currentWorkingDays = $daysInMonth - count($nonWorkingDays);

            // Calculate what working days SHOULD be without holidays (for proportional scaling)
            $weekendsOnly = $weekends;
            $workingDaysWithoutHolidays = $daysInMonth - count($weekendsOnly);

            // If no holidays in this month, skip recalculation
            if ($currentWorkingDays == $workingDaysWithoutHolidays) {
                continue;
            }

            // Get all activities for this month that are WORKING_STANDART
            $activities = \viki\Service\Models\Elequent\WorkPlaceActivity::where('date', $monthDate)
                ->where('type_working', \viki\Service\Models\Elequent\WorkPlaceActivity::WORKING_STANDART)
                ->where('copied', \viki\Service\Models\Elequent\WorkPlaceActivity::COPIED_ACTIVITY)
                ->get();

            foreach ($activities as $activity) {
                $hoursRecord = \viki\Service\Models\Elequent\HoursActivityByMonth::where('work_place_activity_id', $activity->id)
                    ->where('date', $monthDate)
                    ->first();

                if (!$hoursRecord) {
                    continue;
                }

                $oldConfiguredHours = $hoursRecord->hours_for_person;

                // Calculate new hours by scaling proportionally based on working days change
                // This preserves the hours-per-day ratio (e.g., 3 hours/day stays 3 hours/day)
                $scaleFactor = $currentWorkingDays / $workingDaysWithoutHolidays;
                $newConfiguredHours = round($oldConfiguredHours * $scaleFactor, 2);

                // Update the configured hours
                $hoursRecord->hours_for_person = $newConfiguredHours;
                $hoursRecord->save();

                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            $this->line("Updated {$updatedCount} configured hours records.");
        } else {
            $this->line("No configured hours needed updating.");
        }
    }
}
