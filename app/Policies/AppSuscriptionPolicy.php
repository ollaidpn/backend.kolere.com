<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AppSuscription;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppSuscriptionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the appSuscription can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the appSuscription can view the model.
     */
    public function view(User $user, AppSuscription $model): bool
    {
        return true;
    }

    /**
     * Determine whether the appSuscription can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the appSuscription can update the model.
     */
    public function update(User $user, AppSuscription $model): bool
    {
        return true;
    }

    /**
     * Determine whether the appSuscription can delete the model.
     */
    public function delete(User $user, AppSuscription $model): bool
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
     * Determine whether the appSuscription can restore the model.
     */
    public function restore(User $user, AppSuscription $model): bool
    {
        return false;
    }

    /**
     * Determine whether the appSuscription can permanently delete the model.
     */
    public function forceDelete(User $user, AppSuscription $model): bool
    {
        return false;
    }
}
