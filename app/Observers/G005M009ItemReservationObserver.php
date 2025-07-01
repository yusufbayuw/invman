<?php

namespace App\Observers;

use App\Models\G002M007Item;
use App\Models\G005M009ItemReservation;
use App\Models\G005M016ItemReservationDetail;

class G005M009ItemReservationObserver
{
    /**
     * Handle the G005M009ItemReservation "created" event.
     */
    public function created(G005M009ItemReservation $g005M009ItemReservation): void
    {
        $g005M009ItemReservation->status = 'menunggu persetujuan'; // Set initial status to 'menunggu persetujuan'
        $g005M009ItemReservation->saveQuietly();
        // Update available_quantity efficiently using Eloquent's decrement method
        $item = G002M007Item::find($g005M009ItemReservation->g002_m007_item_id);
        $item->decrement('available_quantity', $g005M009ItemReservation->quantity);
        // Fetch available item instances based on the reservation quantity
        // change status of item instances to reserved
        $instances = $item->item_instance()
            ->where('is_available', true)
            ->take($g005M009ItemReservation->quantity)
            ->get();
        foreach ($instances as $instance) {
            $instance->status = $g005M009ItemReservation->status;
            $instance->is_available = false; // Mark as not available
            $instance->saveQuietly();

            G005M016ItemReservationDetail::create([
                'g005_m009_item_reservation_id' => $g005M009ItemReservation->id,
                'g002_m015_item_instance_id' => $instance->id,
            ]);
        }
    }

    /**
     * Handle the G005M009ItemReservation "updated" event.
     */
    public function updated(G005M009ItemReservation $g005M009ItemReservation): void
    {
        /**
         * Observer method to handle changes in the 'status' attribute of a G005M009ItemReservation model.
         *
         * - When the 'status' is changed to 'disetujui/dipinjamkan':
         *   - Retrieves all related item instances.
         *   - Updates each instance's status to match the reservation's status.
         *   - Saves the changes quietly (without firing events).
         *
         * - When the 'status' is changed to 'dikembalikan':
         *   - Retrieves all related item instances.
         *   - Sets each instance's status to "tersedia" (available) and marks it as available.
         *   - Saves the changes quietly.
         *   - Updates the reservation's status to "tersedia" and saves it quietly.
         *   - Finds the related item (G002M007Item) and increments its available quantity by the reservation's quantity.
         *   - Saves the item quietly.
         *
         * This ensures that item instances and their parent item reflect the correct status and availability
         * when a reservation is approved, lent out, or returned.
         */
        if ($g005M009ItemReservation->isDirty('status')) {
            if ($g005M009ItemReservation->status === 'disetujui/dipinjamkan') {
                // relasi dari item reservation ke item instance adalah melalui hasMany item reservation detail, item reservation detail memiliki relasi belongTo ke item instance

                $instances = $g005M009ItemReservation->item_reservation_detail
                    ->map(function ($detail) {
                        return $detail->item_instance;
                    });
                foreach ($instances as $instance) {
                    $instance->status = $g005M009ItemReservation->status;
                    $instance->saveQuietly();
                }
            } elseif ($g005M009ItemReservation->status === 'dikembalikan') {
                $instances = $g005M009ItemReservation->item_instance;
                foreach ($instances as $instance) {
                    $instance->status = "tersedia";
                    $instance->is_available = true;
                    $instance->saveQuietly();
                }

                $g005M009ItemReservation->status = 'tersedia'; 
                $g005M009ItemReservation->saveQuietly();
                
                $item = G002M007Item::find($g005M009ItemReservation->g002_m007_item_id);
                if ($item) {
                    $item->increment('available_quantity', $g005M009ItemReservation->quantity); 
                    $item->saveQuietly();
                }
            }
        }
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
