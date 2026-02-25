<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AlertMessage;
use Illuminate\Auth\Access\HandlesAuthorization;

class AlertMessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the alertMessage can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the alertMessage can view the model.
     */
    public function view(User $user, AlertMessage $model): bool
    {
        return true;
    }

    /**
     * Determine whether the alertMessage can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the alertMessage can update the model.
     */
    public function update(User $user, AlertMessage $model): bool
    {
        return true;
    }

    /**
     * Determine whether the alertMessage can delete the model.
     */
    public function delete(User $user, AlertMessage $model): bool
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
     * Determine whether the alertMessage can restore the model.
     */
    public function restore(User $user, AlertMessage $model): bool
    {
        return false;
    }

    /**
     * Determine whether the alertMessage can permanently delete the model.
     */
    public function forceDelete(User $user, AlertMessage $model): bool
    {
        return false;
    }
}
