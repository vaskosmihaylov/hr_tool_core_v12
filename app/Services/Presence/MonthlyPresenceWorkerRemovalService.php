<?php

namespace App\Services\Presence;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\WorkPlaceActivity;

class MonthlyPresenceWorkerRemovalService
{
    public function removeWorkerFromActivity(
        int $workplaceId,
        int $activityId,
        int $workerId,
        string|DateTimeInterface $month
    ): void {
        $monthStart = Carbon::parse($month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $normalizedDate = $monthStart->toDateString();

        $activity = WorkPlaceActivity::query()
            ->whereKey($activityId)
            ->where('work_place_id', $workplaceId)
            ->first();

        if (! $activity) {
            throw new RuntimeException('Избраната дейност не принадлежи на този обект.');
        }

        DB::transaction(function () use ($workplaceId, $activityId, $workerId, $monthStart, $monthEnd, $normalizedDate): void {
            WorkerRecord::query()
                ->where('worker_id', $workerId)
                ->where('work_place_id', $workplaceId)
                ->where('work_place_activity_id', $activityId)
                ->whereBetween('date', [
                    $monthStart->toDateString(),
                    $monthEnd->toDateString(),
                ])
                ->delete();

            DB::table('viki_work_place_activity_worker')
                ->where('work_place_activity_id', $activityId)
                ->where('worker_id', $workerId)
                ->where('date', $normalizedDate)
                ->delete();

            if ($this->hasRemainingActivityAssignment($workplaceId, $workerId, $normalizedDate)) {
                return;
            }

            DB::table('viki_work_place_worker')
                ->where('work_place_id', $workplaceId)
                ->where('worker_id', $workerId)
                ->where('date', $normalizedDate)
                ->delete();
        });
    }

    private function hasRemainingActivityAssignment(int $workplaceId, int $workerId, string $normalizedDate): bool
    {
        return DB::table('viki_work_place_activity_worker')
            ->join(
                'viki_work_place_activity',
                'viki_work_place_activity.id',
                '=',
                'viki_work_place_activity_worker.work_place_activity_id'
            )
            ->where('viki_work_place_activity.work_place_id', $workplaceId)
            ->where('viki_work_place_activity_worker.worker_id', $workerId)
            ->where('viki_work_place_activity_worker.date', $normalizedDate)
            ->exists();
    }
}
