<?php

namespace App\Services;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use viki\Service\Models\Elequent\Vacation;

class VacationOverlapService
{
    public function findOverlap(
        int|string $workerId,
        string|DateTimeInterface $startDate,
        string|DateTimeInterface $endDate,
        int|string|null $ignoreVacationId = null
    ): ?Vacation {
        $startDate = Carbon::parse($startDate)->toDateString();
        $endDate = Carbon::parse($endDate)->toDateString();

        return Vacation::query()
            ->where('worker_id', $workerId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->where(function (Builder $query): void {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 2);
            })
            ->when($ignoreVacationId !== null, function (Builder $query) use ($ignoreVacationId): void {
                $query->whereKeyNot($ignoreVacationId);
            })
            ->orderBy('start_date')
            ->first();
    }
}
