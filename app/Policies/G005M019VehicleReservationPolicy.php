<?php

namespace App\Policies;

use App\Models\G005M019VehicleReservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class G005M019VehicleReservationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isFacility() || $user->isSarpras() || $user->isAssetManager();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, G005M019VehicleReservation $g005M019VehicleReservation): bool
    {
        return $user->managesReservation($g005M019VehicleReservation)
            || $user->belongsToUnit($g005M019VehicleReservation->activity?->g001_m001_unit_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, G005M019VehicleReservation $g005M019VehicleReservation): bool
    {
        return $user->managesReservation($g005M019VehicleReservation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, G005M019VehicleReservation $g005M019VehicleReservation): bool
    {
        return $user->isAdmin() && $g005M019VehicleReservation->status === \App\Enums\ReservationStatus::Draft->value;
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, G005M019VehicleReservation $g005M019VehicleReservation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, G005M019VehicleReservation $g005M019VehicleReservation): bool
    {
        return $user->can('restore_g005::m019::vehicle::reservation');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_g005::m019::vehicle::reservation');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, G005M019VehicleReservation $g005M019VehicleReservation): bool
    {
        return $user->can('replicate_g005::m019::vehicle::reservation');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_g005::m019::vehicle::reservation');
    }
}
