<?php

namespace App\Filament\Service\Resources\PresenceResource\Pages;

use App\Filament\Service\Resources\PresenceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePresenceRecord extends CreateRecord
{
    protected static string $resource = PresenceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Записът за присъствие е създаден успешно!';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creator_id'] = auth()->id();
        return $data;
    }
}
