<?php

namespace App\Filament\Service\Resources\RegionResource\Pages;

use App\Filament\Service\Resources\RegionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditRegion extends EditRecord
{
    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('Преглед'),
            Actions\DeleteAction::make()
                ->label('Изтриване')
                ->requiresConfirmation()
                ->modalHeading('Изтриване на регион')
                ->modalDescription('Сигурни ли сте, че искате да изтриете този регион? Това действие не може да бъде отменено.')
                ->modalSubmitActionLabel('Да, изтрий'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Регион обновен')
            ->body('Данните на региона бяха успешно актуализирани.');
    }

    protected function afterSave(): void
    {
        // Log activity
        activity()
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('обновен регион: ' . $this->record->name);
    }
}
