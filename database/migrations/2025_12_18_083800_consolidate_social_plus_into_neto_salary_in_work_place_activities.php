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
     * This migration consolidates the legacy "social_plus" field (Социален пакет)
     * into the "neto_salary" field for all activities from the old application.
     *
     * Background: The old application had a separate "Социален пакет" field
     * which was typically set to 10 лв. This caused activities to show incorrect
     * amounts (e.g., Нето заплата = 1,390 лв + Социални = 10 лв = Total 1,400 лв).
     *
     * Solution: Add social_plus to neto_salary and set social_plus to 0.
     * After: Нето заплата = 1,400 лв + Социални = 0 лв = Total 1,400 лв
     */
    public function up(): void
    {
        // Update all activities where social_plus > 0
        // Add social_plus to neto_salary and then set social_plus to 0
        $affected = DB::update('
            UPDATE viki_work_place_activity
            SET
                neto_salary = neto_salary + social_plus,
                social_plus = 0
            WHERE social_plus > 0
        ');

        // Log the results using Laravel's logger
        \Log::info("Consolidated social_plus into neto_salary for {$affected} activities.");
    }

    /**
     * Reverse the migrations.
     *
     * NOTE: This down migration cannot perfectly restore the original state
     * because we don't know which activities originally had social_plus values
     * and what those values were. This is a data migration, not a schema change.
     *
     * If you need to rollback, you should restore from a database backup.
     */
    public function down(): void
    {
        \Log::warning('Cannot reverse this data migration without a backup.');
        \Log::warning('The social_plus values have been permanently consolidated into neto_salary.');
        \Log::warning('If you need to restore the original data, use a database backup.');
    }
};
