<?php

namespace viki\Service\Models\Elequent;

use Illuminate\Database\Eloquent\Model;

class WorkPlaceActivityMonthSnapshot extends Model
{
    protected $table = "viki_work_place_activity_month_snapshots";

    protected $fillable = [
        "work_place_id",
        "base_activity_id",
        "date",
        "activity",
        "type_working",
        "neto_salary",
        "worker_count",
        "hours_per_day",
        "created_by",
        "updated_by",
    ];

    protected $casts = [
        "neto_salary" => "float",
        "worker_count" => "integer",
        "type_working" => "integer",
        "hours_per_day" => "float",
    ];

    public function workplace()
    {
        return $this->belongsTo(WorkPlace::class, "work_place_id");
    }

    public function baseActivity()
    {
        return $this->belongsTo(WorkPlaceActivity::class, "base_activity_id");
    }

    /**
     * Get all snapshots for a given workplace and month.
     * Returns a collection keyed by base_activity_id.
     */
    public static function getForMonth(
        int $workPlaceId,
        string $normalizedDate
    ): \Illuminate\Support\Collection {
        return static::where("work_place_id", $workPlaceId)
            ->where("date", $normalizedDate)
            ->get()
            ->keyBy("base_activity_id");
    }

    /**
     * Create or update snapshots for all base activities in a workplace for a given month.
     */
    public static function snapshotActivities(
        int $workPlaceId,
        string $normalizedDate,
        ?int $userId = null
    ): void {
        $activities = WorkPlaceActivity::where("work_place_id", $workPlaceId)
            ->whereNull("date")
            ->where("copied", WorkPlaceActivity::NOT_COPIED_ACTIVITY)
            ->get();

        foreach ($activities as $activity) {
            $hoursPerDay = WorkPlaceActivityHoursPerDay::findHoursPerDayPerActivity(
                $activity->id
            );

            static::updateOrCreate(
                [
                    "work_place_id" => $workPlaceId,
                    "base_activity_id" => $activity->id,
                    "date" => $normalizedDate,
                ],
                [
                    "activity" => $activity->activity,
                    "type_working" => $activity->type_working,
                    "neto_salary" => $activity->neto_salary,
                    "worker_count" => $activity->worker_count ?? 1,
                    "hours_per_day" => $hoursPerDay ?: null,
                    "updated_by" => $userId,
                    "created_by" => $userId,
                ]
            );
        }
    }
}
