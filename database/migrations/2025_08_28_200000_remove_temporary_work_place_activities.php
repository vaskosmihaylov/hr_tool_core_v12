<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $activityIds = DB::table('viki_work_place_activity')
            ->whereNotNull('date')
            ->where('copied', 0)
            ->pluck('id');

        if ($activityIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($activityIds): void {
            DB::table('viki_work_place_activity_worker')
                ->whereIn('work_place_activity_id', $activityIds)
                ->delete();

            DB::table('viki_hours_activity_by_month')
                ->whereIn('work_place_activity_id', $activityIds)
                ->delete();

            DB::table('viki_worker_records')
                ->whereIn('work_place_activity_id', $activityIds)
                ->delete();

            DB::table('viki_work_place_activity')
                ->whereIn('id', $activityIds)
                ->delete();
        });
    }

    public function down(): void
    {
        // Temporary activities are removed permanently.
    }
};
