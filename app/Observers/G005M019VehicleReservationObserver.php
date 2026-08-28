<?php

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\G005M019VehicleReservation;
use App\Services\LoanRequestService;

class G005M019VehicleReservationObserver
{
    public function updating(G005M019VehicleReservation $reservation): void
    {
        $this->recordDecision($reservation);
    }

    /**
     * Handle the G005M019VehicleReservation "created" event.
     */
    public function created(G005M019VehicleReservation $g005M019VehicleReservation): void
    {
        if (! $g005M019VehicleReservation->status) {
            $g005M019VehicleReservation->status = ReservationStatus::Submitted->value;
            $g005M019VehicleReservation->saveQuietly();
        }

        if ($g005M019VehicleReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M019VehicleReservation->activity);
        }
    }

    /**
     * Handle the G005M019VehicleReservation "updated" event.
     */
    public function updated(G005M019VehicleReservation $g005M019VehicleReservation): void
    {
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
        if (! $reservation->isDirty('status') || ! in_array($reservation->status, [
            ReservationStatus::Approved->value,
            ReservationStatus::Rejected->value,
        ], true)) {
            return;
        }

        app(LoanRequestService::class)->assertDecisionAllowed($reservation);

        $reservation->decision_by = auth()->id();
        $reservation->decision_at = now();

        if ($reservation->status === ReservationStatus::Approved->value) {
            $reservation->rejection_reason = null;
        }
    }
}
