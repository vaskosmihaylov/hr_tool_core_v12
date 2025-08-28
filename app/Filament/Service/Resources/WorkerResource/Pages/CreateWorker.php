<?php

namespace App\Filament\Service\Resources\WorkerResource\Pages;

use App\Filament\Service\Resources\WorkerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateWorker extends CreateRecord
{
    protected static string $resource = WorkerResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Работник създаден')
            ->body('Служителят беше успешно добавен в системата.');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure region validation
        if (empty($data['region_id'])) {
            throw new \Exception('Изберете регион!');
        }

        if (empty($data['work_place_id'])) {
            throw new \Exception('Изберете обект!');
        }

        if (empty($data['work_place_activity_id'])) {
            throw new \Exception('Изберете дейност!');
        }

        // Handle empty note field - convert empty string to null
        if (isset($data['note']) && trim($data['note']) === '') {
            $data['note'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Log activity
        activity()
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('създаден служител: ' . $this->record->name . ' ' . $this->record->middle_name . ' ' . $this->record->family_name);
    }
}
