<?php

namespace App\Observers;

use App\Models\G005M009ItemReservation;

class G005M009ItemReservationObserver
{
    /**
     * Handle the G005M009ItemReservation "created" event.
     */
    public function created(G005M009ItemReservation $g005M009ItemReservation): void
    {
        
    }

    /**
     * Handle the G005M009ItemReservation "updated" event.
     */
    public function updated(G005M009ItemReservation $g005M009ItemReservation): void
    {
        //
    }

    /**
     * Handle the G005M009ItemReservation "deleted" event.
     */
    public function deleted(G005M009ItemReservation $g005M009ItemReservation): void
    {
        //
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
}
