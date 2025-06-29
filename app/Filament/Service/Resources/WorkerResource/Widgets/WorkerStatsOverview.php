<?php

namespace App\Filament\Service\Resources\WorkerResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\Vacation;
use viki\Service\Models\Elequent\WorkerBonus;

class WorkerStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalWorkers = Worker::count();
        $activeWorkers = Worker::where('status', Worker::WORKER_ACTIVE)->count();
        $inactiveWorkers = Worker::where('status', Worker::WORKER_INACTIVE)->count();
        
        $pendingVacations = Vacation::where('status', 0)->count();
        $pendingBonuses = WorkerBonus::where('status', 0)->count();
        
        $totalSalary = Worker::where('status', Worker::WORKER_ACTIVE)
            ->sum('neto_salary');

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

            Stat::make('Чакащи отпуски', $pendingVacations)
                ->description('Отпуски за одобрение')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning'),

            Stat::make('Чакащи бонуси/глоби', $pendingBonuses)
                ->description('Бонуси и глоби за одобрение')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Обща заплата', number_format($totalSalary, 2) . ' лв.')
                ->description('Сума от заплати на активни работници')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
