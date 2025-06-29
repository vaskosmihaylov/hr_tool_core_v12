<?php

namespace App\Filament\Service\Resources\RegionResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use viki\Service\Models\Elequent\Region;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkPlace;

class RegionStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRegions = Region::count();
        $activeRegions = Region::where('status', Region::REGION_ACTIVE)->count();
        $inactiveRegions = Region::where('status', Region::REGION_UNACTIVE)->count();
        
        $totalWorkplaces = WorkPlace::count();
        $activeWorkplaces = WorkPlace::where('status', WorkPlace::WORK_PLACE_ACTIVE)->count();
        
        $workersInRegions = Worker::whereHas('region')->count();

        return [
            Stat::make('Общо региони', $totalRegions)
                ->description('Всички региони в системата')
                ->descriptionIcon('heroicon-m-map')
                ->color('primary'),

            Stat::make('Активни региони', $activeRegions)
                ->description('Региони в активен статус')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Неактивни региони', $inactiveRegions)
                ->description('Региони в неактивен статус')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Общо обекти', $totalWorkplaces)
                ->description('Обекти във всички региони')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),

            Stat::make('Активни обекти', $activeWorkplaces)
                ->description('Активни обекти')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),

            Stat::make('Работници в региони', $workersInRegions)
                ->description('Работници назначени в региони')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}
