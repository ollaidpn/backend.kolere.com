<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Manager;
use Illuminate\Auth\Access\HandlesAuthorization;

class ManagerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the manager can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the manager can view the model.
     */
    public function view(User $user, Manager $model): bool
    {
        return true;
    }

    /**
     * Determine whether the manager can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the manager can update the model.
     */
    public function update(User $user, Manager $model): bool
    {
        return true;
    }

    /**
     * Determine whether the manager can delete the model.
     */
    public function delete(User $user, Manager $model): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete multiple instances of the model.
     */
    public function deleteAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the manager can restore the model.
     */
    public function restore(User $user, Manager $model): bool
    {
        return false;
    }

    /**
     * Determine whether the manager can permanently delete the model.
     */
    public function forceDelete(User $user, Manager $model): bool
    {
        return false;
    }
}
