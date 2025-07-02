<?php

namespace App\Observers;

use App\Models\G005M010RoomReservation;

class G005M010RoomReservationObserver
{
    /**
     * Handle the G005M010RoomReservation "created" event.
     */
    public function created(G005M010RoomReservation $g005M010RoomReservation): void
    {
        // Set status reservasi menjadi 'menunggu persetujuan'
        $g005M010RoomReservation->status = 'menunggu persetujuan';
        $g005M010RoomReservation->saveQuietly();
    }

    /**
     * Handle the G005M010RoomReservation "updated" event.
     */
    public function updated(G005M010RoomReservation $g005M010RoomReservation): void
    {
        //
    }

    /**
     * Handle the G005M010RoomReservation "deleted" event.
     */
    public function deleted(G005M010RoomReservation $g005M010RoomReservation): void
    {
        //
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
}
