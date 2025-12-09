<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\Pages;

use App\Filament\Service\Resources\WorkPlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use viki\Service\Models\Elequent\WorkPlace;

class ListWorkPlaces extends ListRecords
{
    protected static string $resource = WorkPlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Създай обект'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WorkPlaceResource\Widgets\WorkPlaceStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'всички' => Tab::make('Всички обекти'),
            
            'активни' => Tab::make('Активни')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', WorkPlace::WORK_PLACE_ACTIVE))
                ->badge(WorkPlace::query()->where('status', WorkPlace::WORK_PLACE_ACTIVE)->count()),
            
            'неактивни' => Tab::make('Неактивни')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', WorkPlace::WORK_PLACE_UNACTIVE))
                ->badge(WorkPlace::query()->where('status', WorkPlace::WORK_PLACE_UNACTIVE)->count()),
        ];
    }
}
