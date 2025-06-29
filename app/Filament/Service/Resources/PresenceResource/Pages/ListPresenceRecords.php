<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Viki\Service\Models\Elequent\WorkerRecord;

class ListPresenceRecords extends ListRecords
{
    protected static string $resource = PresenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Нов запис'),
            
            Actions\Action::make('presence_table')
                ->label('Таблица за присъствие')
                ->icon('heroicon-o-table-cells')
                ->color('info')
                ->url('/service/presences/table/1'),
                
            Actions\Action::make('monthly_view')
                ->label('Месечен преглед')
                ->icon('heroicon-o-calendar')
                ->color('warning')
                ->url('/service/presences/monthly/1'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PresenceResource\Widgets\PresenceStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'всички' => Tab::make('Всички записи'),
            
            'чакащи' => Tab::make('Чакащи одобрение')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', WorkerRecord::WORKER_RECORD_WAITING))
                ->badge(WorkerRecord::query()->where('status', WorkerRecord::WORKER_RECORD_WAITING)->count()),
            
            'одобрени' => Tab::make('Одобрени')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', WorkerRecord::WORKER_RECORD_APPROVED))
                ->badge(WorkerRecord::query()->where('status', WorkerRecord::WORKER_RECORD_APPROVED)->count()),
                
            'приключени' => Tab::make('Приключени')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', WorkerRecord::WORKER_RECORD_FINISHED))
                ->badge(WorkerRecord::query()->where('status', WorkerRecord::WORKER_RECORD_FINISHED)->count()),

            'днес' => Tab::make('Днес')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('date', today()))
                ->badge(WorkerRecord::query()->whereDate('date', today())->count()),

            'тази_седмица' => Tab::make('Тази седмица')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('date', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]))
                ->badge(WorkerRecord::query()->whereBetween('date', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count()),
        ];
    }
}
