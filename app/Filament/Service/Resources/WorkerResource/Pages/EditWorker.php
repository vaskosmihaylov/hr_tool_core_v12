<?php

namespace App\Filament\Service\Resources\WorkerResource\Pages;

use App\Filament\Service\Resources\WorkerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditWorker extends EditRecord
{
    protected static string $resource = WorkerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Запази')
                ->color('success')
                ->action('save')
                ->keyBindings(['mod+s']),
            Actions\DeleteAction::make()
                ->label('Изтриване')
                ->visible(fn (): bool => WorkerResource::canDeleteWorkers()),
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
            ->title('Работник обновен')
            ->body('Данните на служителя бяха успешно актуализирани.');
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

        // Handle empty note field - keep empty string to satisfy legacy NOT NULL constraint
        if (!isset($data['note']) || trim((string) $data['note']) === '') {
            $data['note'] = '';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Log activity
        activity()
            ->performedOn($this->record)
            ->causedBy(auth()->user())
            ->withProperties(['customProperty' => 'customValue'])
            ->log('обновен служител: ' . $this->record->name . ' ' . $this->record->middle_name . ' ' . $this->record->family_name);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Запази')
                ->color('success'),
            $this->getCancelFormAction(),
        ];
    }
}
