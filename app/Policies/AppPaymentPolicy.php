<?php

namespace App\Policies;

use App\Models\User;
use App\Models\AppPayment;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppPaymentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the appPayment can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the appPayment can view the model.
     */
    public function view(User $user, AppPayment $model): bool
    {
        return true;
    }

    /**
     * Determine whether the appPayment can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the appPayment can update the model.
     */
    public function update(User $user, AppPayment $model): bool
    {
        return true;
    }

    /**
     * Determine whether the appPayment can delete the model.
     */
    public function delete(User $user, AppPayment $model): bool
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
     * Determine whether the appPayment can restore the model.
     */
    public function restore(User $user, AppPayment $model): bool
    {
        return false;
    }

    /**
     * Determine whether the appPayment can permanently delete the model.
     */
    public function forceDelete(User $user, AppPayment $model): bool
    {
        return false;
    }
}
