<?php

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\G005M019VehicleReservation;
use App\Services\LoanRequestService;
use Illuminate\Validation\ValidationException;

class G005M019VehicleReservationObserver
{
    public function creating(G005M019VehicleReservation $reservation): void
    {
        $reservation->status ??= $reservation->activity?->status === ReservationStatus::Draft->value
            ? ReservationStatus::Draft->value
            : ReservationStatus::Submitted->value;
    }

    public function updating(G005M019VehicleReservation $reservation): void
    {
        $this->recordDecision($reservation);
    }

    public function deleting(G005M019VehicleReservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Draft->value) {
            throw ValidationException::withMessages([
                'status' => 'Reservasi yang sudah diajukan tidak boleh dihapus.',
            ]);
        }
    }

    /**
     * Handle the G005M019VehicleReservation "created" event.
     */
    public function created(G005M019VehicleReservation $g005M019VehicleReservation): void
    {
        if ($g005M019VehicleReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M019VehicleReservation->activity);
        }
    }

    /**
     * Handle the G005M019VehicleReservation "updated" event.
     */
    public function updated(G005M019VehicleReservation $g005M019VehicleReservation): void
    {
        app(LoanRequestService::class)->recordStatusHistory($g005M019VehicleReservation);

        if ($g005M019VehicleReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M019VehicleReservation->activity);
        }
    }

    /**
     * Handle the G005M019VehicleReservation "deleted" event.
     */
    public function deleted(G005M019VehicleReservation $g005M019VehicleReservation): void
    {
        if ($g005M019VehicleReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M019VehicleReservation->activity);
        }
    }

    /**
     * Handle the G005M019VehicleReservation "restored" event.
     */
    public function restored(G005M019VehicleReservation $g005M019VehicleReservation): void
    {
        //
    }

    /**
     * Handle the G005M019VehicleReservation "force deleted" event.
     */
    public function forceDeleted(G005M019VehicleReservation $g005M019VehicleReservation): void
    {
        //
    }

    private function recordDecision(G005M019VehicleReservation $reservation): void
    {
        app(LoanRequestService::class)->assertReservationTransitionAllowed($reservation);
    }
}
