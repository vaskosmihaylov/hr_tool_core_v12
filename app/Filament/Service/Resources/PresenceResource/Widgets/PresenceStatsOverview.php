<?php

namespace App\Filament\Service\Resources\PresenceResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use viki\Service\Models\Elequent\WorkerRecord;
use viki\Service\Models\Elequent\Worker;

class PresenceStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRecords = WorkerRecord::count();
        $pendingRecords = WorkerRecord::where('status', WorkerRecord::WORKER_RECORD_WAITING)->count();
        $approvedRecords = WorkerRecord::where('status', WorkerRecord::WORKER_RECORD_APPROVED)->count();
        $todayRecords = WorkerRecord::whereDate('date', today())->count();
        $totalHours = WorkerRecord::where('status', WorkerRecord::WORKER_RECORD_APPROVED)->sum('hours');
        $activeWorkers = Worker::where('status', Worker::WORKER_ACTIVE)->count();

        return [
            Stat::make('Общо записи', number_format($totalRecords))
                ->description('Всички записи за присъствие')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('primary'),

            Stat::make('Чакащи одобрение', $pendingRecords)
                ->description('Записи за проверка')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Одобрени записи', number_format($approvedRecords))
                ->description('Потвърдени записи')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Записи днес', $todayRecords)
                ->description('Присъствие за днешна дата')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('info'),

            Stat::make('Общо часове', number_format($totalHours, 1) . ' ч.')
                ->description('Одобрени работни часове')
                ->descriptionIcon('heroicon-o-clock')
                ->color('success'),

            Stat::make('Активни работници', $activeWorkers)
                ->description('Работници в системата')
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),
        ];
    }
}
