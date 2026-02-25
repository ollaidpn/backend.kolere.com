<?php

namespace App\Policies;

use App\Models\Link;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class LinkPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the link can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the link can view the model.
     */
    public function view(User $user, Link $model): bool
    {
        return true;
    }

    /**
     * Determine whether the link can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the link can update the model.
     */
    public function update(User $user, Link $model): bool
    {
        return true;
    }

    /**
     * Determine whether the link can delete the model.
     */
    public function delete(User $user, Link $model): bool
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
     * Determine whether the link can restore the model.
     */
    public function restore(User $user, Link $model): bool
    {
        return false;
    }

    /**
     * Determine whether the link can permanently delete the model.
     */
    public function forceDelete(User $user, Link $model): bool
    {
        return false;
    }
}
