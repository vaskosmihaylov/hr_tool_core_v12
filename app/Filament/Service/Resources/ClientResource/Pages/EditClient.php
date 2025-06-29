<?php

namespace App\Filament\Service\Resources\ClientResource\Pages;

use App\Filament\Service\Resources\ClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Преглед'),
            Actions\DeleteAction::make()
                ->label('Изтриване'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Клиентът е обновен успешно!';
    }
}
