<?php

namespace App\Filament\Service\Resources\ClientResource\Pages;

use App\Filament\Service\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use viki\Service\Models\Elequent\Client;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Създай клиент'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClientResource\Widgets\ClientStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'всички' => Tab::make('Всички клиенти'),
            
            'активни' => Tab::make('Активни')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Client::CLIENT_ACTIVE))
                ->badge(Client::query()->where('status', Client::CLIENT_ACTIVE)->count()),
            
            'неактивни' => Tab::make('Неактивни')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Client::CLIENT_UNACTIVE))
                ->badge(Client::query()->where('status', Client::CLIENT_UNACTIVE)->count()),

            'с_обекти' => Tab::make('С обекти')
                ->modifyQueryUsing(fn (Builder $query) => $query->has('workplaces'))
                ->badge(Client::query()->has('workplaces')->count()),
        ];
    }
}
