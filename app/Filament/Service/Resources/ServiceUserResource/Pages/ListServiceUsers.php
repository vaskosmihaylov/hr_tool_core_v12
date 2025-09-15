<?php

namespace App\Filament\Service\Resources\ServiceUserResource\Pages;

use App\Filament\Service\Resources\ServiceUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceUsers extends ListRecords
{
    protected static string $resource = ServiceUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Добави потребител')
                ->visible(fn (): bool => ServiceUserResource::canManageUsers()),
        ];
    }
    
    public function getTitle(): string
    {
        return 'Потребители';
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            // Add widgets if needed
        ];
    }
}
