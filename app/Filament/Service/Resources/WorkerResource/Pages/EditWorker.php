<?php

namespace App\Filament\Service\Resources\WorkerResource\Pages;

use App\Filament\Service\Resources\WorkerResource;
use App\Services\Presence\PresenceConfigurationService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;

class EditWorker extends EditRecord
{
    protected static string $resource = WorkerResource::class;

    protected ?int $originalWorkplaceId = null;

    protected ?int $originalActivityId = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->originalWorkplaceId = $data['work_place_id'] ?? null;
        $this->originalActivityId = $data['work_place_activity_id'] ?? null;

        return parent::mutateFormDataBeforeFill($data);
    }

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
        $workPlaceId = isset($data['work_place_id']) && $data['work_place_id'] !== '' ? (int) $data['work_place_id'] : null;
        $activityId = isset($data['work_place_activity_id']) && $data['work_place_activity_id'] !== '' ? (int) $data['work_place_activity_id'] : null;
        $regionId = isset($data['region_id']) && $data['region_id'] !== '' ? (int) $data['region_id'] : null;

        if ($regionId === null) {
            throw new \Exception('Регионът е задължителен.');
        }

        $selectedWorkPlace = null;
        if ($workPlaceId !== null) {
            $selectedWorkPlace = WorkPlace::find($workPlaceId);
            if (!$selectedWorkPlace) {
                throw new \Exception('Избраният обект не съществува.');
            }

            if ($regionId !== (int) $selectedWorkPlace->region_id) {
                throw new \Exception('Избраният обект не принадлежи към избрания регион.');
            }
        }

        if ($activityId !== null) {
            $selectedActivity = WorkPlaceActivity::find($activityId);
            if (!$selectedActivity) {
                throw new \Exception('Избраната дейност не съществува.');
            }

            if ($workPlaceId === null) {
                $workPlaceId = (int) $selectedActivity->work_place_id;
                $data['work_place_id'] = $workPlaceId;
            } elseif ($workPlaceId !== (int) $selectedActivity->work_place_id) {
                throw new \Exception('Избраната дейност не принадлежи към избрания обект.');
            }

            if ($selectedActivity->date !== null || (int) $selectedActivity->copied === WorkPlaceActivity::COPIED_ACTIVITY) {
                $baseActivity = WorkPlaceActivity::query()
                    ->where('work_place_id', $workPlaceId)
                    ->whereNull('date')
                    ->where('copied', WorkPlaceActivity::NOT_COPIED_ACTIVITY)
                    ->where('activity', $selectedActivity->activity)
                    ->where('type_working', $selectedActivity->type_working)
                    ->orderByDesc('id')
                    ->first();

                if (!$baseActivity) {
                    throw new \Exception('Избраната дейност няма базов еквивалент.');
                }

                $data['work_place_activity_id'] = $baseActivity->id;
            }

        } else {
            $data['work_place_activity_id'] = 0;
        }

        if (!isset($data['hours_per_day']) || $data['hours_per_day'] === '') {
            $data['hours_per_day'] = 8;
        }

        if (!isset($data['neto_salary']) || $data['neto_salary'] === '') {
            $data['neto_salary'] = 0;
        }

        if (!isset($data['income']) || $data['income'] === '') {
            $data['income'] = 0;
        }

        if (!isset($data['work_place_id']) || $data['work_place_id'] === '') {
            $data['work_place_id'] = 0;
        }

        // Handle empty note field - keep empty string to satisfy legacy NOT NULL constraint
        if (!isset($data['note']) || trim((string) $data['note']) === '') {
            $data['note'] = '';
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $workplaceChanged = $this->originalWorkplaceId !== $this->record->work_place_id;
        $activityChanged = $this->originalActivityId !== $this->record->work_place_activity_id;

        if ($workplaceChanged || $activityChanged) {
            $this->syncWorkerPresenceForCurrentMonth();
        }

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

    private function syncWorkerPresenceForCurrentMonth(): void
    {
        $workplaceId = (int) ($this->record->work_place_id ?? 0);
        $activityId = (int) ($this->record->work_place_activity_id ?? 0);

        if ($workplaceId === 0 || $activityId === 0) {
            return;
        }

        $selectedActivity = WorkPlaceActivity::find($activityId);

        if (!$selectedActivity || $selectedActivity->work_place_id !== $workplaceId) {
            return;
        }

        $targetActivity = $selectedActivity;

        // Always attach worker presence to a base activity.
        if ($selectedActivity->date !== null || (int) $selectedActivity->copied === WorkPlaceActivity::COPIED_ACTIVITY) {
            $baseActivity = WorkPlaceActivity::query()
                ->where('work_place_id', $workplaceId)
                ->whereNull('date')
                ->where('copied', WorkPlaceActivity::NOT_COPIED_ACTIVITY)
                ->where('activity', $selectedActivity->activity)
                ->where('type_working', $selectedActivity->type_working)
                ->orderByDesc('id')
                ->first();

            if (!$baseActivity) {
                return;
            }

            $targetActivity = $baseActivity;
        }

        try {
            PresenceConfigurationService::addWorkerToActivity(
                $workplaceId,
                $targetActivity->id,
                $this->record->id,
                Carbon::now()->format('m-Y')
            );
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'вече е добавен')) {
                return;
            }

            if (str_contains($exception->getMessage(), 'Месецът е заключен')) {
                Notification::make()
                    ->title('Текущият месец е заключен')
                    ->body('Промяната по служителя е запазена, но не беше приложена в месечното присъствие.')
                    ->warning()
                    ->send();
                return;
            }

            throw $exception;
        }
    }
}
