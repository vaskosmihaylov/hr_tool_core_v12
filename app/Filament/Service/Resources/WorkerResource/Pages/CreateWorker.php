<?php

namespace App\Filament\Service\Resources\WorkerResource\Pages;

use App\Filament\Service\Resources\WorkerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use viki\Service\Models\Elequent\WorkPlace;
use viki\Service\Models\Elequent\WorkPlaceActivity;

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

        if (!isset($data['work_place_activity_id']) || $data['work_place_activity_id'] === '') {
            $data['work_place_activity_id'] = 0;
        }

        // Ensure note defaults to an empty string for older schemas where the column is non-nullable
        if (!isset($data['note']) || trim((string) $data['note']) === '') {
            $data['note'] = '';
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
