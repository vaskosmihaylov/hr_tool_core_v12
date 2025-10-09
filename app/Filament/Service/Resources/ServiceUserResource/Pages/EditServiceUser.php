<?php

namespace App\Filament\Service\Resources\ServiceUserResource\Pages;

use App\Filament\Service\Resources\ServiceUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditServiceUser extends EditRecord
{
    protected static string $resource = ServiceUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Изтриване')
                ->visible(fn (): bool => ServiceUserResource::canDeleteUsers()),
        ];
    }
    
    public function getTitle(): string
    {
        return 'Редактиране на потребител';
    }
    
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Потребителят е обновен успешно';
    }
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load current user data
        $user = $this->record;
        
        // Get user's current role
        $userRole = $user->roles->first();
        if ($userRole) {
            $data['user_role'] = $userRole->name;
        }
        
        // Get user's region (single for managers)
        $data['user_regions'] = $user->regions->pluck('id')->toArray();

        // Get user's workplaces (multiple for supervisors)
        $data['user_workplaces'] = $user->workPlaces->pluck('id')->toArray();
        
        return $data;
    }
    
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract custom fields
        $roleName = $data['user_role'] ?? null;
        $regionIds = $data['user_regions'] ?? [];
        $workplaceIds = $data['user_workplaces'] ?? [];
        
        // Clean data for User model
        $userData = $this->prepareUserData($data);
        
        // Update the user
        $record->update($userData);
        
        // Update role and relationships
        $this->updateUserRole($record, $roleName);
        $this->updateUserRegions($record, $regionIds);
        $this->updateUserWorkplaces($record, $workplaceIds);
        
        return $record;
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Prepare user data by removing custom fields
     */
    private function prepareUserData(array $data): array
    {
        unset($data['user_role'], $data['user_regions'], $data['user_workplaces'], $data['password_confirmation']);
        return $data;
    }

    /**
     * Update user role
     */
    private function updateUserRole(Model $record, ?string $roleName): void
    {
        if ($roleName) {
            $record->syncRoles([$roleName]);
        } else {
            $record->syncRoles([]);
        }
    }

    /**
     * Sync selected regions with the user.
     */
    private function updateUserRegions(Model $record, array $regionIds): void
    {
        $record->regions()->sync($regionIds);
    }

    /**
     * Sync selected workplaces with the user.
     */
    private function updateUserWorkplaces(Model $record, array $workplaceIds): void
    {
        $record->workPlaces()->sync($workplaceIds);
    }
}
