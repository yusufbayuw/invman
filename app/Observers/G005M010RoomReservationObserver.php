<?php

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\G005M010RoomReservation;
use App\Services\LoanRequestService;

class G005M010RoomReservationObserver
{
    public function updating(G005M010RoomReservation $reservation): void
    {
        $this->recordDecision($reservation);
    }

    /**
     * Handle the G005M010RoomReservation "created" event.
     */
    public function created(G005M010RoomReservation $g005M010RoomReservation): void
    {
        if (! $g005M010RoomReservation->status) {
            $g005M010RoomReservation->status = ReservationStatus::Submitted->value;
            $g005M010RoomReservation->saveQuietly();
        }

        if ($g005M010RoomReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M010RoomReservation->activity);
        }
    }

    /**
     * Handle the G005M010RoomReservation "updated" event.
     */
    public function updated(G005M010RoomReservation $g005M010RoomReservation): void
    {
        app(LoanRequestService::class)->recordStatusHistory($g005M010RoomReservation);

        if ($g005M010RoomReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M010RoomReservation->activity);
        }
    }

    /**
     * Handle the G005M010RoomReservation "deleted" event.
     */
    public function deleted(G005M010RoomReservation $g005M010RoomReservation): void
    {
        if ($g005M010RoomReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M010RoomReservation->activity);
        }
    }

    /**
     * Handle the G005M010RoomReservation "restored" event.
     */
    public function restored(G005M010RoomReservation $g005M010RoomReservation): void
    {
        //
    }

    /**
     * Handle the G005M010RoomReservation "force deleted" event.
     */
    public function forceDeleted(G005M010RoomReservation $g005M010RoomReservation): void
    {
        //
    }

    private function recordDecision(G005M010RoomReservation $reservation): void
    {
        app(LoanRequestService::class)->assertReservationTransitionAllowed($reservation);
    }
}
