<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Entity;
use Illuminate\Auth\Access\HandlesAuthorization;

class EntityPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the entity can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the entity can view the model.
     */
    public function view(User $user, Entity $model): bool
    {
        return true;
    }

    /**
     * Determine whether the entity can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the entity can update the model.
     */
    public function update(User $user, Entity $model): bool
    {
        return true;
    }

    /**
     * Determine whether the entity can delete the model.
     */
    public function delete(User $user, Entity $model): bool
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
     * Determine whether the entity can restore the model.
     */
    public function restore(User $user, Entity $model): bool
    {
        return false;
    }

    /**
     * Determine whether the entity can permanently delete the model.
     */
    public function forceDelete(User $user, Entity $model): bool
    {
        return false;
    }
}
