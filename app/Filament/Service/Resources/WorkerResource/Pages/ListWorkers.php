<?php

namespace App\Filament\Service\Resources\WorkerResource\Pages;

use App\Filament\Service\Resources\WorkerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use viki\Service\Models\Elequent\Worker;

class ListWorkers extends ListRecords
{
    protected static string $resource = WorkerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Нов работник')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Всички')
                ->badge(Worker::count()),
                
            'active' => Tab::make('Активни')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Worker::WORKER_ACTIVE))
                ->badge(Worker::where('status', Worker::WORKER_ACTIVE)->count())
                ->badgeColor('success'),
                
            'inactive' => Tab::make('Неактивни')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Worker::WORKER_INACTIVE))
                ->badge(Worker::where('status', Worker::WORKER_INACTIVE)->count())
                ->badgeColor('danger'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WorkerResource\Widgets\WorkerStatsOverview::class,
        ];
    }
}
