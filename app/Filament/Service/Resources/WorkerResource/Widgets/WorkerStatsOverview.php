<?php

namespace App\Filament\Service\Resources\WorkerResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use viki\Service\Models\Elequent\Worker;

class WorkerStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalWorkers = Worker::count();
        $activeWorkers = Worker::where('status', Worker::WORKER_ACTIVE)->count();
        $inactiveWorkers = Worker::where('status', Worker::USER_UNACTIVE)->count();

        return [
            Stat::make('Общо работници', $totalWorkers)
                ->description('Всички работници в системата')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Активни работници', $activeWorkers)
                ->description('Работници в активен статус')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Неактивни работници', $inactiveWorkers)
                ->description('Работници в неактивен статус')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
