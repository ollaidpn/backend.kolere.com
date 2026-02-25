<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Auth\Access\HandlesAuthorization;

class AdminPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the admin can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view the model.
     */
    public function view(User $user, Admin $model): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can update the model.
     */
    public function update(User $user, Admin $model): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can delete the model.
     */
    public function delete(User $user, Admin $model): bool
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
     * Determine whether the admin can restore the model.
     */
    public function restore(User $user, Admin $model): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can permanently delete the model.
     */
    public function forceDelete(User $user, Admin $model): bool
    {
        return false;
    }
}
