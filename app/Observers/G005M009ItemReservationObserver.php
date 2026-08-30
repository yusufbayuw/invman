<?php

namespace App\Observers;

use App\Enums\ReservationStatus;
use App\Models\G005M009ItemReservation;
use App\Services\LoanRequestService;
use Illuminate\Validation\ValidationException;

class G005M009ItemReservationObserver
{
    public function creating(G005M009ItemReservation $reservation): void
    {
        $reservation->status ??= $reservation->activity?->status === ReservationStatus::Draft->value
            ? ReservationStatus::Draft->value
            : ReservationStatus::Submitted->value;
    }

    public function updating(G005M009ItemReservation $reservation): void
    {
        $this->recordDecision($reservation);
    }

    public function deleting(G005M009ItemReservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::Draft->value) {
            throw ValidationException::withMessages([
                'status' => 'Reservasi yang sudah diajukan tidak boleh dihapus.',
            ]);
        }
    }

    /**
     * Handle the G005M009ItemReservation "created" event.
     */
    public function created(G005M009ItemReservation $g005M009ItemReservation): void
    {
        if ($g005M009ItemReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M009ItemReservation->activity);
        }

    }

    /**
     * Handle the G005M009ItemReservation "updated" event.
     */
    public function updated(G005M009ItemReservation $g005M009ItemReservation): void
    {

        app(LoanRequestService::class)->recordStatusHistory($g005M009ItemReservation);

        if ($g005M009ItemReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M009ItemReservation->activity);
        }

    }

    /**
     * Handle the G005M009ItemReservation "deleted" event.
     */
    public function deleted(G005M009ItemReservation $g005M009ItemReservation): void
    {
        if ($g005M009ItemReservation->activity) {
            app(LoanRequestService::class)->syncStatus($g005M009ItemReservation->activity);
        }
    }

    /**
     * Handle the G005M009ItemReservation "restored" event.
     */
    public function restored(G005M009ItemReservation $g005M009ItemReservation): void
    {
        //
    }

    /**
     * Handle the G005M009ItemReservation "force deleted" event.
     */
    public function forceDeleted(G005M009ItemReservation $g005M009ItemReservation): void
    {
        //
    }

    private function recordDecision(G005M009ItemReservation $reservation): void
    {
        app(LoanRequestService::class)->assertReservationTransitionAllowed($reservation);
    }
}
