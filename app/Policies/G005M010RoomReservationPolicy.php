<?php

namespace App\Policies;

use App\Models\User;
use App\Models\G005M010RoomReservation;
use Illuminate\Auth\Access\HandlesAuthorization;

class G005M010RoomReservationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, G005M010RoomReservation $g005M010RoomReservation): bool
    {
        return $user->can('view_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, G005M010RoomReservation $g005M010RoomReservation): bool
    {
        return $user->can('update_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, G005M010RoomReservation $g005M010RoomReservation): bool
    {
        return $user->can('delete_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, G005M010RoomReservation $g005M010RoomReservation): bool
    {
        return $user->can('force_delete_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, G005M010RoomReservation $g005M010RoomReservation): bool
    {
        return $user->can('restore_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, G005M010RoomReservation $g005M010RoomReservation): bool
    {
        return $user->can('replicate_g005::m010::room::reservation');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_g005::m010::room::reservation');
    }
}
