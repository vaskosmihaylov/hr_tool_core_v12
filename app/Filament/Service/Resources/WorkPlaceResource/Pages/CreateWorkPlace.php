<?php

namespace App\Filament\Service\Resources\WorkPlaceResource\Pages;

use App\Filament\Service\Resources\WorkPlaceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CreateWorkPlace extends CreateRecord
{
    protected static string $resource = WorkPlaceResource::class;

    public function mount(): void
    {
        // Security check: Supervisors cannot create workplaces
        $user = auth()->user();
        if ($user && $user->hasRole('supervisor')) {
            throw new AccessDeniedHttpException('Нямате достъп за създаване на обекти.');
        }

        // Additional check using the resource's canCreate method
        if (!static::getResource()::canCreate()) {
            throw new AccessDeniedHttpException('Нямате достъп за създаване на обекти.');
        }

        parent::mount();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Обектът е създаден успешно!';
    }
}
