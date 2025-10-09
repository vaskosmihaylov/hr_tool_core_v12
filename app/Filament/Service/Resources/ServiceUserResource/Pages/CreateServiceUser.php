<?php

namespace App\Filament\Service\Resources\ServiceUserResource\Pages;

use App\Filament\Service\Resources\ServiceUserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateServiceUser extends CreateRecord
{
    protected static string $resource = ServiceUserResource::class;
    
    public function getTitle(): string
    {
        return 'Създаване на потребител';
    }
    
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Потребителят е създаден успешно';
    }
    
    protected function handleRecordCreation(array $data): Model
    {
        // Extract custom fields
        $roleName = $data['user_role'] ?? null;
        $regionIds = $data['user_regions'] ?? [];
        $workplaceIds = $data['user_workplaces'] ?? [];
        
        // Clean data for User model
        $userData = $this->prepareUserData($data);
        
        // Create the user
        $user = User::create($userData);
        
        // Assign role and relationships
        $this->assignUserRole($user, $roleName);
        $this->assignUserRegions($user, $regionIds);
        $this->assignUserWorkplaces($user, $workplaceIds);
        
        return $user;
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
     * Assign role to user
     */
    private function assignUserRole(User $user, ?string $roleName): void
    {
        if ($roleName) {
            $user->assignRole($roleName);
        }
    }

    /**
     * Sync selected regions with the user.
     */
    private function assignUserRegions(User $user, array $regionIds): void
    {
        $user->regions()->sync($regionIds);
    }

    /**
     * Sync selected workplaces with the user.
     */
    private function assignUserWorkplaces(User $user, array $workplaceIds): void
    {
        $user->workPlaces()->sync($workplaceIds);
    }
}
