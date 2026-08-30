<?php

namespace App\Observers;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use Illuminate\Validation\ValidationException;

class G002M015ItemInstanceObserver
{
    public function deleting(G002M015ItemInstance $instance): void
    {
        if ($instance->item_reservation_detail()->exists()
            || $instance->item_history()->exists()
            || $instance->item_review()->exists()
            || $instance->item_instance_checklist()->exists()) {
            throw ValidationException::withMessages([
                'item_instance' => 'Barang satuan yang memiliki histori tidak boleh dihapus. Nonaktifkan status pinjamnya.',
            ]);
        }
    }

    public function creating(G002M015ItemInstance $instance): void
    {
        $item = G002M007Item::query()->find($instance->g002_m007_item_id);

        if (! $item) {
            return;
        }

        $sequence = 1;
        $existingCodes = $item->item_instance()->pluck('code');
        $codePrefix = filled($item->code) ? $item->code.'-' : '';

        while ($existingCodes->contains($codePrefix.$sequence)) {
            $sequence++;
        }

        $instance->g001_m001_unit_id ??= $item->g001_m001_unit_id;
        $instance->g003_m006_room_id ??= $item->g003_m006_room_id;
        $instance->is_borrowable ??= (bool) $item->is_borrowable;
        $instance->is_available ??= true;
        $instance->status ??= 'tersedia';
        $instance->name ??= trim($item->name.' '.$sequence);
        $instance->code ??= $codePrefix.$sequence;
    }

    /**
     * Handle the G002M015ItemInstance "created" event.
     */
    public function created(G002M015ItemInstance $instance): void
    {
        $this->syncItemTotals($instance->g002_m007_item_id);
    }

    /**
     * Handle the G002M015ItemInstance "updated" event.
     */
    public function updated(G002M015ItemInstance $instance): void
    {
        if (! $instance->wasChanged(['g002_m007_item_id', 'is_borrowable', 'is_available'])) {
            return;
        }

        $this->syncItemTotals($instance->g002_m007_item_id);

        $originalItemId = $instance->getOriginal('g002_m007_item_id');
        if ($originalItemId && (string) $originalItemId !== (string) $instance->g002_m007_item_id) {
            $this->syncItemTotals($originalItemId);
        }
    }

    /**
     * Handle the G002M015ItemInstance "deleted" event.
     */
    public function deleted(G002M015ItemInstance $instance): void
    {
        $this->syncItemTotals($instance->g002_m007_item_id);
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

    private function syncItemTotals(int|string|null $itemId): void
    {
        if (! $itemId) {
            return;
        }

        $item = G002M007Item::query()->find($itemId);

        if (! $item) {
            return;
        }

        $instances = $item->item_instance();
        $item->quantity = (clone $instances)->count();
        $item->available_quantity = (clone $instances)
            ->where('is_available', true)
            ->where('is_borrowable', true)
            ->count();
        $item->saveQuietly();
    }
}
