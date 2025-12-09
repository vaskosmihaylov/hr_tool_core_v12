<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * List of tables to convert from MyISAM to InnoDB
     */
    private array $tables = [
        'pages',
        'password_resets',
        'permission_role',
        'role_user',
        'viki_approvements',
        'viki_archive',
        'viki_client_region',
        'viki_clients',
        'viki_comments',
        'viki_hours_activity_by_month',
        'viki_hours_per_day_activity',
        'viki_manager_region',
        'viki_regions',
        'viki_salary',
        'viki_special_days',
        'viki_supervisor_work_place',
        'viki_user_region',
        'viki_vacations',
        'viki_work_place',
        'viki_work_place_activity',
        'viki_work_place_activity_by_month',
        'viki_work_place_activity_worker',
        'viki_work_place_worker',
        'viki_worker_records',
        'viki_workers',
        'viki_workplace_month_budget',
    ];

    /**
     * Run the migrations.
     *
     * Convert all MyISAM tables to InnoDB to support transactions
     * and fix GTID consistency errors.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            // Check if table exists before converting
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
                echo "Converted {$table} to InnoDB\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Convert tables back to MyISAM (not recommended in production)
     */
    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE=MyISAM");
                echo "Reverted {$table} to MyISAM\n";
            }
        }
    }
};
