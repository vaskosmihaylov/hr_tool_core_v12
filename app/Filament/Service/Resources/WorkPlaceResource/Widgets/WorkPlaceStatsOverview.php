<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Viki\Service\Models\Elequent\WorkPlace;
use Viki\Service\Models\Elequent\Worker;
use Viki\Service\Models\Elequent\Region;

class WorkPlaceStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalWorkPlaces = WorkPlace::count();
        $activeWorkPlaces = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)->count();
        $totalBudget = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)->sum('budget');
        $workersInWorkPlaces = Worker::where('status', Worker::WORKER_ACTIVE)->whereNotNull('work_place_id')->count();

        return [
            Stat::make('Общо обекти', $totalWorkPlaces)
                ->description('Всички регистрирани обекти')
                ->descriptionIcon('heroicon-o-building-office')
                ->color('primary'),

            Stat::make('Активни обекти', $activeWorkPlaces)
                ->description('Действащи в момента')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Общ бюджет', number_format($totalBudget, 0) . ' лв.')
                ->description('Сума от всички активни обекти')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Работници в обекти', $workersInWorkPlaces)
                ->description('Назначени на работни места')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),
        ];
    }
}
