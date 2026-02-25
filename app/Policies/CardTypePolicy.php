<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CardType;
use Illuminate\Auth\Access\HandlesAuthorization;

class CardTypePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the cardType can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the cardType can view the model.
     */
    public function view(User $user, CardType $model): bool
    {
        return true;
    }

    /**
     * Determine whether the cardType can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the cardType can update the model.
     */
    public function update(User $user, CardType $model): bool
    {
        return true;
    }

    /**
     * Determine whether the cardType can delete the model.
     */
    public function delete(User $user, CardType $model): bool
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
     * Determine whether the cardType can restore the model.
     */
    public function restore(User $user, CardType $model): bool
    {
        return false;
    }

    /**
     * Determine whether the cardType can permanently delete the model.
     */
    public function forceDelete(User $user, CardType $model): bool
    {
        return false;
    }
}
