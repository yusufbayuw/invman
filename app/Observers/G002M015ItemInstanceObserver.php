<?php

namespace App\Observers;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;

class G002M015ItemInstanceObserver
{
    /**
     * Handle the G002M015ItemInstance "created" event.
     */
    public function created(G002M015ItemInstance $g002M015ItemInstance): void
    {
        $item = G002M007Item::where('id', $g002M015ItemInstance->g002_m007_item_id)->first();
        if ($item) {    
            $item->quantity += 1; // Increment quantity
            if ($g002M015ItemInstance->is_borrowable) {
                $item->available_quantity += 1; // Increment available quantity
            }
            $item->saveQuietly();
        }
    }

    /**
     * Handle the G002M015ItemInstance "updated" event.
     */
    public function updated(G002M015ItemInstance $g002M015ItemInstance): void
    {
        if ($g002M015ItemInstance->isDirty('is_borrowable')) {
            $item = G002M007Item::where('id', $g002M015ItemInstance->g002_m007_item_id)->first();
            if ($item) {
                $item->available_quantity += $g002M015ItemInstance->is_borrowable ? 1 : -1;
                $item->saveQuaietly();
            }
        }
    }

    /**
     * Handle the G002M015ItemInstance "deleted" event.
     */
    public function deleted(G002M015ItemInstance $g002M015ItemInstance): void
    {
        $item = G002M007Item::where('id', $g002M015ItemInstance->g002_m007_item_id)->first();
        if ($item) {    
            $item->quantity -= 1; // Increment quantity
            if ($g002M015ItemInstance->is_borrowable) {
                $item->available_quantity -= 1; // Increment available quantity
            }
            $item->saveQuietly();
        }
    }

    /**
     * Handle the G002M015ItemInstance "restored" event.
     */
    public function restored(G002M015ItemInstance $g002M015ItemInstance): void
    {
        //
    }

    /**
     * Handle the G002M015ItemInstance "force deleted" event.
     */
    public function forceDeleted(G002M015ItemInstance $g002M015ItemInstance): void
    {
        //
    }
}
