<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use viki\Service\Models\Elequent\WorkPlaceActivityMonthSnapshot;

class BackfillActivitySnapshots extends Command
{
    protected $signature = "snapshots:backfill";
    protected $description = "Create activity snapshots for all already-locked months";

    public function handle(): int
    {
        $locks = DB::table("viki_monthly_presence_locks")
            ->where("is_locked", true)
            ->get();

        $this->info("Found {$locks->count()} locked months to backfill.");

        $bar = $this->output->createProgressBar($locks->count());
        $bar->start();

        foreach ($locks as $lock) {
            $normalizedDate = sprintf(
                "%04d-%02d-01",
                $lock->year,
                $lock->month
            );

            WorkPlaceActivityMonthSnapshot::snapshotActivities(
                $lock->work_place_id,
                $normalizedDate,
                $lock->locked_by
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $total = DB::table("viki_work_place_activity_month_snapshots")->count();
        $this->info("Done. Total snapshots in database: {$total}");

        return self::SUCCESS;
    }
}
