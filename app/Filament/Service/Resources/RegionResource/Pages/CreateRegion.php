<?php

namespace App\Filament\Service\Resources\RegionResource\Pages;

use App\Filament\Service\Resources\RegionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateRegion extends CreateRecord
{
    protected static string $resource = RegionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Регион създаден')
            ->body('Регионът беше успешно добавен в системата.');
    }

    protected function afterCreate(): void
    {
        // Log activity
        activity()
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('създаден регион: ' . $this->record->name);
    }
}
