<?php

namespace App\Policies;

use App\Models\User;
use App\Models\G002M015ItemInstance;
use Illuminate\Auth\Access\HandlesAuthorization;

class G002M015ItemInstancePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, G002M015ItemInstance $g002M015ItemInstance): bool
    {
        return $user->can('view_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, G002M015ItemInstance $g002M015ItemInstance): bool
    {
        return $user->can('update_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, G002M015ItemInstance $g002M015ItemInstance): bool
    {
        return $user->can('delete_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, G002M015ItemInstance $g002M015ItemInstance): bool
    {
        return $user->can('force_delete_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, G002M015ItemInstance $g002M015ItemInstance): bool
    {
        return $user->can('restore_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, G002M015ItemInstance $g002M015ItemInstance): bool
    {
        return $user->can('replicate_g002::m015::item::instance');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_g002::m015::item::instance');
    }
}
