<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pricing;
use Illuminate\Auth\Access\HandlesAuthorization;

class PricingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the pricing can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the pricing can view the model.
     */
    public function view(User $user, Pricing $model): bool
    {
        return true;
    }

    /**
     * Determine whether the pricing can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the pricing can update the model.
     */
    public function update(User $user, Pricing $model): bool
    {
        return true;
    }

    /**
     * Determine whether the pricing can delete the model.
     */
    public function delete(User $user, Pricing $model): bool
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
     * Determine whether the pricing can restore the model.
     */
    public function restore(User $user, Pricing $model): bool
    {
        return false;
    }

    /**
     * Determine whether the pricing can permanently delete the model.
     */
    public function forceDelete(User $user, Pricing $model): bool
    {
        return false;
    }
}
