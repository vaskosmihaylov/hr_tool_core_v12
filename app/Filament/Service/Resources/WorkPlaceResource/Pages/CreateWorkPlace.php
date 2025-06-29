<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\Pages;

use App\Filament\Service\Resources\WorkPlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkPlace extends CreateRecord
{
    protected static string $resource = WorkPlaceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Обектът е създаден успешно!';
    }
}
