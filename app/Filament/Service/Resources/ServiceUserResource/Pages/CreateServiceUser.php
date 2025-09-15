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
        $regionId = $data['user_region'] ?? null;
        $workplaceIds = $data['user_workplaces'] ?? [];
        
        // Clean data for User model
        $userData = $this->prepareUserData($data);
        
        // Create the user
        $user = User::create($userData);
        
        // Assign role and relationships
        $this->assignUserRole($user, $roleName);
        $this->assignUserRegions($user, $roleName, $regionId);
        $this->assignUserWorkplaces($user, $roleName, $workplaceIds);
        
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
        unset($data['user_role'], $data['user_region'], $data['user_workplaces'], $data['password_confirmation']);
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
     * Assign regions to user (for managers)
     */
    private function assignUserRegions(User $user, ?string $roleName, ?int $regionId): void
    {
        if ($roleName === 'manager' && $regionId) {
            $user->regions()->sync([$regionId]);
        }
    }

    /**
     * Assign workplaces to user (for supervisors)
     */
    private function assignUserWorkplaces(User $user, ?string $roleName, array $workplaceIds): void
    {
        if ($roleName === 'supervisor' && !empty($workplaceIds)) {
            $user->workPlaces()->sync($workplaceIds);
        }
    }
}
