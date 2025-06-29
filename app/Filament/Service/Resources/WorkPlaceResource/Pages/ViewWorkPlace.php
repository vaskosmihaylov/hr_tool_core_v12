<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\Pages;

use App\Filament\Service\Resources\WorkPlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkPlace extends ViewRecord
{
    protected static string $resource = WorkPlaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Редактиране'),
        ];
    }
}
