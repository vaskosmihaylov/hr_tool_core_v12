<?php

namespace App\Filament\Service\Resources\RegionResource\Pages;

use App\Filament\Service\Resources\RegionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use viki\Service\Models\Elequent\Region;

class ListRegions extends ListRecords
{
    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Нов регион')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Всички')
                ->badge(Region::count()),
                
            'active' => Tab::make('Активни')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Region::REGION_ACTIVE))
                ->badge(Region::where('status', Region::REGION_ACTIVE)->count())
                ->badgeColor('success'),
                
            'inactive' => Tab::make('Неактивни')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Region::REGION_UNACTIVE))
                ->badge(Region::where('status', Region::REGION_UNACTIVE)->count())
                ->badgeColor('danger'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RegionResource\Widgets\RegionStatsOverview::class,
        ];
    }
}
