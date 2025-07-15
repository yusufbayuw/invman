<?php

namespace App\Policies;

use App\Models\User;
use App\Models\G008M018Driver;
use Illuminate\Auth\Access\HandlesAuthorization;

class G008M018DriverPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_g008::m018::driver');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, G008M018Driver $g008M018Driver): bool
    {
        return $user->can('view_g008::m018::driver');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_g008::m018::driver');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, G008M018Driver $g008M018Driver): bool
    {
        return $user->can('update_g008::m018::driver');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, G008M018Driver $g008M018Driver): bool
    {
        return $user->can('delete_g008::m018::driver');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_g008::m018::driver');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, G008M018Driver $g008M018Driver): bool
    {
        return $user->can('force_delete_g008::m018::driver');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_g008::m018::driver');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, G008M018Driver $g008M018Driver): bool
    {
        return $user->can('restore_g008::m018::driver');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_g008::m018::driver');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, G008M018Driver $g008M018Driver): bool
    {
        return $user->can('replicate_g008::m018::driver');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_g008::m018::driver');
    }
}
