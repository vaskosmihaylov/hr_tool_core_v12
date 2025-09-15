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
                ->visible(fn (): bool => ServiceUserResource::canManageUsers()),
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
        $userRegion = $user->regions->first();
        if ($userRegion) {
            $data['user_region'] = $userRegion->id;
        }
        
        // Get user's workplaces (multiple for supervisors)
        $data['user_workplaces'] = $user->workPlaces->pluck('id')->toArray();
        
        return $data;
    }
    
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Extract custom fields
        $roleName = $data['user_role'] ?? null;
        $regionId = $data['user_region'] ?? null;
        $workplaceIds = $data['user_workplaces'] ?? [];
        
        // Clean data for User model
        $userData = $this->prepareUserData($data);
        
        // Update the user
        $record->update($userData);
        
        // Update role and relationships
        $this->updateUserRole($record, $roleName);
        $this->updateUserRegions($record, $roleName, $regionId);
        $this->updateUserWorkplaces($record, $roleName, $workplaceIds);
        
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
        unset($data['user_role'], $data['user_region'], $data['user_workplaces'], $data['password_confirmation']);
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
     * Update user regions (for managers)
     */
    private function updateUserRegions(Model $record, ?string $roleName, ?int $regionId): void
    {
        if ($roleName === 'manager' && $regionId) {
            $record->regions()->sync([$regionId]);
        } else {
            // Clear regions if not a manager or no region selected
            $record->regions()->sync([]);
        }
    }

    /**
     * Update user workplaces (for supervisors)
     */
    private function updateUserWorkplaces(Model $record, ?string $roleName, array $workplaceIds): void
    {
        if ($roleName === 'supervisor' && !empty($workplaceIds)) {
            $record->workPlaces()->sync($workplaceIds);
        } else {
            // Clear workplaces if not a supervisor or no workplaces selected
            $record->workPlaces()->sync([]);
        }
    }
}
