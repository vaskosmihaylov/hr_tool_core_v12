<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use viki\Service\Models\Elequent\Vacation;
use viki\Service\Models\Elequent\Worker;

class VacationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canManageVacations($user);
    }

    public function view(User $user, Vacation $vacation): bool
    {
        if (! $this->canManageVacations($user)) {
            return false;
        }

        if (! $user->hasRole('supervisor')) {
            return true;
        }

        return $this->canAccessWorker($user, $vacation->worker);
    }

    public function create(User $user): bool
    {
        return $this->canManageVacations($user);
    }

    public function update(User $user, Vacation $vacation): bool
    {
        return $this->view($user, $vacation);
    }

    public function delete(User $user, Vacation $vacation): bool
    {
        return $this->view($user, $vacation);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManageVacations($user);
    }

    public function forceDelete(User $user, Vacation $vacation): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Vacation $vacation): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Vacation $vacation): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function canManageVacations(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super_admin', 'manager', 'supervisor']);
    }

    private function canAccessWorker(User $user, ?Worker $worker): bool
    {
        if (! $worker || ! $worker->work_place_id) {
            return false;
        }

        return $user->workPlaces()
            ->where('viki_work_place.id', $worker->work_place_id)
            ->exists();
    }
}
