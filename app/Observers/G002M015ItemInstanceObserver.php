<?php

namespace App\Observers;

use App\Models\G002M007Item;
use App\Models\G002M015ItemInstance;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

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
        if ($item->isBulk()) {
            throw ValidationException::withMessages(['g002_m007_item_id' => 'Barang bulk tidak boleh memiliki identitas Barang Satuan.']);
        }

        [$sequence, $generatedCode] = app(\App\Services\InventoryIdentityService::class)->nextInstance($item);

        $instance->g001_m001_unit_id ??= $item->g001_m001_unit_id;
        $instance->g003_m006_room_id ??= $item->g003_m006_room_id;
        $instance->is_borrowable ??= (bool) $item->is_borrowable;
        $instance->is_available ??= true;
        $instance->status ??= 'tersedia';
        $instance->name ??= trim($item->name.' '.$sequence);
        $instance->code ??= $generatedCode;
    }

    /**
     * Handle the G002M015ItemInstance "created" event.
     */
    public function created(G002M015ItemInstance $instance): void
    {
        $this->applyTotalChanges([$instance->g002_m007_item_id => [1, $this->ready($instance->is_available, $instance->is_borrowable)]]);
    }

    /**
     * Handle the G002M015ItemInstance "updated" event.
     */
    public function updated(G002M015ItemInstance $instance): void
    {
        // A package definition must never silently turn into a loanable unit.
        if ($instance->is_borrowable && $instance->parentRelations()
            ->whereIn('relation_type', ['component', 'installed'])->exists()) {
            throw ValidationException::withMessages(['is_borrowable' => 'Komponen terpasang tidak boleh dipinjam terpisah.']);
        }
        if ($instance->is_borrowable && $instance->childRelations()->where('relation_type', 'bundle')->exists()) {
            throw ValidationException::withMessages(['is_borrowable' => 'Paket belum mendukung alokasi multi-komponen otomatis.']);
        }
        if (! $instance->wasChanged(['g002_m007_item_id', 'is_borrowable', 'is_available'])) {
            return;
        }
        $oldId = $instance->getOriginal('g002_m007_item_id');
        $newId = $instance->g002_m007_item_id;
        $oldReady = $this->ready($instance->getOriginal('is_available'), $instance->getOriginal('is_borrowable'));
        $newReady = $this->ready($instance->is_available, $instance->is_borrowable);
        if ((string) $oldId === (string) $newId) {
            $this->applyTotalChanges([$newId => [0, $newReady - $oldReady]]);
        } else {
            $this->applyTotalChanges([$oldId => [-1, -$oldReady], $newId => [1, $newReady]]);
        }
    }

    /**
     * Handle the G002M015ItemInstance "deleted" event.
     */
    public function deleted(G002M015ItemInstance $instance): void
    {
        $this->applyTotalChanges([$instance->g002_m007_item_id => [-1, -$this->ready($instance->is_available, $instance->is_borrowable)]]);
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

    private function ready(mixed $available, mixed $borrowable): int
    {
        return $available && $borrowable ? 1 : 0;
    }

    /** Use committed-row arithmetic, not counts from a caller's older snapshot. */
    private function applyTotalChanges(array $changes): void
    {
        ksort($changes, SORT_NUMERIC);
        foreach ($changes as $itemId => [$quantity, $available]) {
            if (! $itemId || ($quantity === 0 && $available === 0)) {
                continue;
            }
            $updates = [];
            if ($quantity !== 0) {
                $updates['quantity'] = DB::raw('quantity + ('.(int) $quantity.')');
            }
            if ($available !== 0) {
                $updates['available_quantity'] = DB::raw('available_quantity + ('.(int) $available.')');
            }
            // One UPDATE retains concurrent increments and rolls back with the instance.
            G002M007Item::query()->whereKey($itemId)->update($updates);
        }
    }
}
