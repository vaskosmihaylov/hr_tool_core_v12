<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use viki\Service\Models\Elequent\Worker;
use viki\Service\Models\Elequent\WorkerBonus;

class WorkerBonusPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canManageBonuses($user);
    }

    public function view(User $user, WorkerBonus $workerBonus): bool
    {
        if (! $this->canManageBonuses($user)) {
            return false;
        }

        if (! $user->hasRole('supervisor')) {
            return true;
        }

        return $this->canAccessWorker($user, $workerBonus->worker);
    }

    public function create(User $user): bool
    {
        return $this->canManageBonuses($user);
    }

    public function update(User $user, WorkerBonus $workerBonus): bool
    {
        return $this->view($user, $workerBonus);
    }

    public function delete(User $user, WorkerBonus $workerBonus): bool
    {
        return $this->view($user, $workerBonus);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManageBonuses($user);
    }

    public function forceDelete(User $user, WorkerBonus $workerBonus): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, WorkerBonus $workerBonus): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, WorkerBonus $workerBonus): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function canManageBonuses(User $user): bool
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
