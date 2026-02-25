<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AlertApp;
use Illuminate\Auth\Access\HandlesAuthorization;

class AlertAppPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the alertApp can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the alertApp can view the model.
     */
    public function view(User $user, AlertApp $model): bool
    {
        return true;
    }

    /**
     * Determine whether the alertApp can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the alertApp can update the model.
     */
    public function update(User $user, AlertApp $model): bool
    {
        return true;
    }

    /**
     * Determine whether the alertApp can delete the model.
     */
    public function delete(User $user, AlertApp $model): bool
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
     * Determine whether the alertApp can restore the model.
     */
    public function restore(User $user, AlertApp $model): bool
    {
        return false;
    }

    /**
     * Determine whether the alertApp can permanently delete the model.
     */
    public function forceDelete(User $user, AlertApp $model): bool
    {
        return false;
    }
}
