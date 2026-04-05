<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create("viki_work_place_activity_month_snapshots", function (
            Blueprint $table
        ) {
            $table->increments("id");
            $table->unsignedInteger("work_place_id");
            $table->unsignedInteger("base_activity_id");
            $table->date("date");
            $table->string("activity");
            $table->unsignedTinyInteger("type_working")->default(0);
            $table->decimal("neto_salary", 12, 4)->default(0);
            $table->unsignedInteger("worker_count")->default(1);
            $table->decimal("hours_per_day", 8, 2)->nullable();
            $table->unsignedInteger("created_by")->nullable();
            $table->unsignedInteger("updated_by")->nullable();
            $table->timestamps();

            $table->unique(
                ["work_place_id", "base_activity_id", "date"],
                "viki_wpa_month_snapshots_unique"
            );
            $table->index(
                ["work_place_id", "date"],
                "viki_wpa_month_snapshots_workplace_date_idx"
            );
            $table->index(
                ["base_activity_id", "date"],
                "viki_wpa_month_snapshots_base_date_idx"
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("viki_work_place_activity_month_snapshots");
    }
};
