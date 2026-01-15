<?php

namespace App\Filament\Service\Resources\WorkerResource\Pages;

use App\Filament\Service\Resources\WorkerResource;
use App\Services\Presence\PresenceConfigurationService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;
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

        if (!$selectedActivity) {
            return;
        }

        $now = Carbon::now();
        $currentMonthStart = $now->copy()->startOfMonth();

        PresenceConfigurationService::ensureMonthlyActivities(
            $workplaceId,
            (int) $now->format('Y'),
            (int) $now->format('m')
        );

        $monthlyActivity = $selectedActivity;

        if ($selectedActivity->date === null || Carbon::parse((string) $selectedActivity->date)->ne($currentMonthStart)) {
            // Find monthly activity (both copied=0 and copied=1)
            $monthlyActivity = WorkPlaceActivity::query()
                ->where('work_place_id', $workplaceId)
                ->whereDate('date', $currentMonthStart->toDateString())
                ->where('activity', $selectedActivity->activity)
                ->where('type_working', $selectedActivity->type_working)
                ->where('neto_salary', $selectedActivity->neto_salary)
                ->orderByDesc('id')
                ->first() ?? $monthlyActivity;
        }

        if (!$monthlyActivity || !$monthlyActivity->date) {
            return;
        }

        try {
            PresenceConfigurationService::addWorkerToActivity(
                $workplaceId,
                $monthlyActivity->id,
                $this->record->id,
                $now->format('m-Y')
            );
        } catch (RuntimeException $exception) {
            if (!str_contains($exception->getMessage(), 'вече е добавен')) {
                throw $exception;
            }
        }
    }
}
