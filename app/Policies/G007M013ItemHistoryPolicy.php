<?php

namespace App\Policies;

use App\Models\User;
use App\Models\G007M013ItemHistory;
use Illuminate\Auth\Access\HandlesAuthorization;

class G007M013ItemHistoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_g007::m013::item::history');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, G007M013ItemHistory $g007M013ItemHistory): bool
    {
        return $user->can('view_g007::m013::item::history');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_g007::m013::item::history');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, G007M013ItemHistory $g007M013ItemHistory): bool
    {
        return $user->can('update_g007::m013::item::history');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, G007M013ItemHistory $g007M013ItemHistory): bool
    {
        return $user->can('delete_g007::m013::item::history');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_g007::m013::item::history');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, G007M013ItemHistory $g007M013ItemHistory): bool
    {
        return $user->can('force_delete_g007::m013::item::history');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_g007::m013::item::history');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, G007M013ItemHistory $g007M013ItemHistory): bool
    {
        return $user->can('restore_g007::m013::item::history');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_g007::m013::item::history');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, G007M013ItemHistory $g007M013ItemHistory): bool
    {
        return $user->can('replicate_g007::m013::item::history');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_g007::m013::item::history');
    }
}
