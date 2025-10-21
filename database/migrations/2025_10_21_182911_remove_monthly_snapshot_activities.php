<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove all monthly snapshot activities (copied = 1 with date NOT NULL).
     * The system will now work directly with base activities (copied = 0, date NULL).
     *
     * WARNING: This will also delete related records:
     * - Worker records (viki_worker_records) that reference monthly snapshot activities
     * - Hours per month (viki_hours_activity_by_month) for snapshot activities
     * - Worker pivot associations (viki_work_place_activity_worker) for snapshot activities
     */
    public function up(): void
    {
        // Get count of monthly snapshots before deletion for logging
        $snapshotCount = DB::table('viki_work_place_activity')
            ->where('copied', 1)
            ->whereNotNull('date')
            ->count();

        echo "Found {$snapshotCount} monthly snapshot activities to delete.\n";

        // Get IDs of monthly snapshot activities
        $snapshotActivityIds = DB::table('viki_work_place_activity')
            ->where('copied', 1)
            ->whereNotNull('date')
            ->pluck('id');

        if ($snapshotActivityIds->isEmpty()) {
            echo "No monthly snapshot activities found. Nothing to delete.\n";
            return;
        }

        // Count related records that will be affected
        $workerRecordsCount = DB::table('viki_worker_records')
            ->whereIn('work_place_activity_id', $snapshotActivityIds)
            ->count();

        $hoursCount = DB::table('viki_hours_activity_by_month')
            ->whereIn('work_place_activity_id', $snapshotActivityIds)
            ->count();

        $workerPivotCount = DB::table('viki_work_place_activity_worker')
            ->whereIn('work_place_activity_id', $snapshotActivityIds)
            ->count();

        echo "This will affect:\n";
        echo "- {$workerRecordsCount} worker records\n";
        echo "- {$hoursCount} hours per month records\n";
        echo "- {$workerPivotCount} worker-activity associations\n";

        DB::transaction(function () use ($snapshotActivityIds) {
            // Delete related worker records (ONLY for copied=1 snapshots)
            DB::table('viki_worker_records')
                ->whereIn('work_place_activity_id', $snapshotActivityIds)
                ->delete();
            echo "Deleted worker records.\n";

            // Delete related hours per month
            DB::table('viki_hours_activity_by_month')
                ->whereIn('work_place_activity_id', $snapshotActivityIds)
                ->delete();
            echo "Deleted hours per month records.\n";

            // Delete related activity hours per day (table might not exist in all installations)
            if (Schema::hasTable('viki_hours_per_day_activity')) {
                DB::table('viki_hours_per_day_activity')
                    ->whereIn('work_place_activity_id', $snapshotActivityIds)
                    ->delete();
                echo "Deleted activity hours per day records.\n";
            }

            // Delete worker-activity pivot associations
            DB::table('viki_work_place_activity_worker')
                ->whereIn('work_place_activity_id', $snapshotActivityIds)
                ->delete();
            echo "Deleted worker-activity associations.\n";

            // Finally, delete ONLY the copied=1 monthly snapshot activities
            // Keep copied=0 activities (both base and monthly instances)
            DB::table('viki_work_place_activity')
                ->where('copied', 1)
                ->whereNotNull('date')
                ->delete();
            echo "Deleted monthly snapshot activities (copied=1 only).\n";
        });

        echo "Migration completed successfully.\n";
    }

    /**
     * Reverse the migrations.
     *
     * Note: This migration is not reversible as we're deleting data.
     * The previous system created monthly snapshots automatically,
     * but we can't recreate them accurately.
     */
    public function down(): void
    {
        // This migration cannot be reversed as we're deleting data
        echo "WARNING: This migration cannot be reversed. Monthly snapshot activities have been permanently deleted.\n";
        echo "The system now works with base activities only.\n";
    }
};
