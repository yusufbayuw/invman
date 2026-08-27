<?php

namespace App\Policies;

use App\Models\User;
use App\Models\G004M008Activity;
use Illuminate\Auth\Access\HandlesAuthorization;

class G004M008ActivityPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isFacility()
            || $user->isSarpras()
            || $user->can('view_any_g004::m008::activity');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, G004M008Activity $g004M008Activity): bool
    {
        if ($user->isSarpras()) {
            return $user->belongsToUnit($g004M008Activity->g001_m001_unit_id);
        }

        return $user->isFacility() || $user->can('view_g004::m008::activity');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isFacility() || ($user->isSarpras() && filled($user->g001_m001_unit_id));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, G004M008Activity $g004M008Activity): bool
    {
        if ($user->isFacility()) {
            return true;
        }

        return $user->belongsToUnit($g004M008Activity->g001_m001_unit_id)
            && in_array($g004M008Activity->status, ['draft', 'submitted'], true);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, G004M008Activity $g004M008Activity): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, G004M008Activity $g004M008Activity): bool
    {
        return $user->can('force_delete_g004::m008::activity');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_g004::m008::activity');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, G004M008Activity $g004M008Activity): bool
    {
        return $user->can('restore_g004::m008::activity');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_g004::m008::activity');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, G004M008Activity $g004M008Activity): bool
    {
        return $user->can('replicate_g004::m008::activity');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_g004::m008::activity');
    }

}
