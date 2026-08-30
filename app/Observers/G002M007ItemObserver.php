<?php

namespace App\Observers;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use Illuminate\Validation\ValidationException;

class G002M007ItemObserver
{
    public function deleting(G002M007Item $item): void
    {
        $hasInstanceHistory = $item->item_instance()
            ->where(function ($query): void {
                $query
                    ->whereHas('item_reservation_detail')
                    ->orWhereHas('item_history')
                    ->orWhereHas('item_review')
                    ->orWhereHas('item_instance_checklist');
            })
            ->exists();

        if ($item->item_reservation()->exists() || $hasInstanceHistory) {
            throw ValidationException::withMessages([
                'item' => 'Barang yang memiliki reservasi atau histori tidak boleh dihapus. Nonaktifkan opsi Dapat Dipinjam.',
            ]);
        }
    }

    public function creating(G002M007Item $item): void
    {
        if (blank($item->quantity)) {
            $item->quantity = 0;

            return;
        }

        if (! is_numeric($item->quantity) || (int) $item->quantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah barang harus berupa angka nol atau lebih.',
            ]);
        }
    }

    public function updating(G002M007Item $item): void
    {
        if ($item->isDirty('quantity')) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah barang dikelola melalui data Barang Satuan.',
            ]);
        }
    }

    /**
     * Handle the G002M007Item "created" event.
     */
    public function created(G002M007Item $g002M007Item): void
    {
        $g002M007Item->available_quantity = $g002M007Item->is_borrowable
            ? $g002M007Item->quantity
            : 0;
        $g002M007Item->status = 'tersedia';
        $g002M007Item->saveQuietly();
        // create item instance based on item quantity
        for ($i = 0; $i < $g002M007Item->quantity; $i++) {
            G002M015ItemInstance::createQuietly([
                'g002_m007_item_id' => $g002M007Item->id,
                'g001_m001_unit_id' => $g002M007Item->g001_m001_unit_id,
                'g003_m006_room_id' => $g002M007Item->g003_m006_room_id,
                'status' => 'tersedia', // default status
                'is_available' => true, // default availability
                'is_borrowable' => (bool) $g002M007Item->is_borrowable,
                'name' => $g002M007Item->name.' '.($i + 1), // unique name for each instance
                'code' => $g002M007Item->code.'-'.($i + 1), // unique code for each instance
            ]);
        }
    }

    /**
     * Handle the G002M007Item "updated" event.
     */
    public function updated(G002M007Item $g002M007Item): void
    {
        // when item's code or name is updated, update all related item instances with number addition
        // check isDirty to avoid unnecessary updates
        if ($g002M007Item->isDirty(['code', 'name'])) {
            $itemInstances = G002M015ItemInstance::where('g002_m007_item_id', $g002M007Item->id)
                ->orderBy('id')
                ->get();
            foreach ($itemInstances as $index => $instance) {
                $instance->updateQuietly([
                    'name' => $g002M007Item->name.' '.($index + 1),
                    'code' => $g002M007Item->code.'-'.($index + 1),
                ]);
            }
        }
    }

    /**
     * Handle the G002M007Item "deleted" event.
     */
    public function deleted(G002M007Item $g002M007Item): void
    {
        //
    }

    /**
     * Handle the G002M007Item "restored" event.
     */
    public function restored(G002M007Item $g002M007Item): void
    {
        //
    }

    /**
     * Handle the G002M007Item "force deleted" event.
     */
    public function forceDeleted(G002M007Item $g002M007Item): void
    {
        //
    }
}
