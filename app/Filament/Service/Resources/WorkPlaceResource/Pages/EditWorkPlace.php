<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\Pages;

use App\Filament\Service\Resources\WorkPlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWorkPlace extends EditRecord
{
    protected static string $resource = WorkPlaceResource::class;

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
        return 'Обектът е обновен успешно!';
    }
}
