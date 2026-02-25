<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AppOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the appOrder can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the appOrder can view the model.
     */
    public function view(User $user, AppOrder $model): bool
    {
        return true;
    }

    /**
     * Determine whether the appOrder can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the appOrder can update the model.
     */
    public function update(User $user, AppOrder $model): bool
    {
        return true;
    }

    /**
     * Determine whether the appOrder can delete the model.
     */
    public function delete(User $user, AppOrder $model): bool
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
     * Determine whether the appOrder can restore the model.
     */
    public function restore(User $user, AppOrder $model): bool
    {
        return false;
    }

    /**
     * Determine whether the appOrder can permanently delete the model.
     */
    public function forceDelete(User $user, AppOrder $model): bool
    {
        return false;
    }
}
