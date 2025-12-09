<?php

namespace App\Filament\Service\Resources\ClientResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use viki\Service\Models\Elequent\Client;
use viki\Service\Models\Elequent\WorkPlace;

class ClientStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalClients = Client::count();
        $activeClients = Client::where('status', Client::CLIENT_ACTIVE)->count();
        $totalBudget = Client::where('status', Client::CLIENT_ACTIVE)->sum('budget');
        $clientsWithWorkplaces = Client::has('workplaces')->count();

        return [
            Stat::make('Общо клиенти', $totalClients)
                ->description('Всички регистрирани клиенти')
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('primary'),

            Stat::make('Активни клиенти', $activeClients)
                ->description('Действащи в момента')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Общ бюджет', number_format($totalBudget, 0) . ' лв.')
                ->description('Сума от всички активни клиенти')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('warning'),

            Stat::make('Клиенти с обекти', $clientsWithWorkplaces)
                ->description('Имат назначени работни места')
                ->descriptionIcon('heroicon-o-building-office')
                ->color('info'),
        ];
    }
}
