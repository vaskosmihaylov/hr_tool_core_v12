<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceUserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_service::user');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->can('view_service::user')) {
            return $this->canAccessUser($user, $model);
        }
        
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_service::user');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->can('update_service::user')) {
            return $this->canAccessUser($user, $model);
        }
        
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->can('delete_service::user')) {
            return $this->canAccessUser($user, $model);
        }
        
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    /**
     * Check if user can access the given user model based on role-based access control.
     */
    private function canAccessUser(User $user, User $model): bool
    {
        $userRoles = $user->roles->pluck('name')->toArray();
        
        // Admin and Super Admin can access all users
        if (in_array('admin', $userRoles) || in_array('super_admin', $userRoles)) {
            return true;
        }
        
        // Manager can access users in their regions
        if (in_array('manager', $userRoles)) {
            $managerRegions = $user->regions->pluck('id')->toArray();
            $modelRegions = $model->regions->pluck('id')->toArray();
            
            // If model has regions that overlap with manager's regions, allow access
            return !empty(array_intersect($managerRegions, $modelRegions));
        }
        
        // Supervisor can access users assigned to their workplaces
        if (in_array('supervisor', $userRoles)) {
            $supervisorWorkplaces = $user->workPlaces->pluck('id')->toArray();
            $modelWorkplaces = $model->workPlaces->pluck('id')->toArray();
            
            // If model has workplaces that overlap with supervisor's workplaces, allow access
            return !empty(array_intersect($supervisorWorkplaces, $modelWorkplaces));
        }
        
        // Default: no access
        return false;
    }
}
