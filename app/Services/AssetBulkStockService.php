<?php

namespace App\Services;

use App\Models\AssetBulkMovement;
use App\Models\G002M007Item;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssetBulkStockService
{
    public function adjust(G002M007Item $item, int $delta, string $reason, User $actor): void
    {
        if ($delta === 0 || mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Perubahan stok harus memiliki jumlah dan alasan minimal lima karakter.']);
        }
        DB::transaction(function () use ($item, $delta, $reason, $actor): void {
            $item = G002M007Item::query()->lockForUpdate()->findOrFail($item->id);
            abort_unless($actor->can('update', $item), 403);
            if (! $item->isBulk()) {
                throw ValidationException::withMessages(['tracking_mode' => 'Stok ini memakai nomor inventaris individual.']);
            }
            // Do not change the physical total while any reservation can still own units.
            if ($item->item_reservation()->whereIn('status', [
                'submitted', 'approved', 'checked_out', 'return_requested', 'partially_approved',
            ])->exists()) {
                throw ValidationException::withMessages(['quantity' => 'Selesaikan reservasi aktif sebelum koreksi total stok bulk.']);
            }
            $newTotal = (int) $item->quantity + $delta;
            if ($newTotal < 0) {
                throw ValidationException::withMessages(['quantity' => 'Stok fisik tidak boleh negatif.']);
            }
            $item->forceFill([
                'quantity' => $newTotal,
                'available_quantity' => $item->is_borrowable ? $newTotal : 0,
            ])->saveQuietly();
            AssetBulkMovement::query()->create([
                'item_id' => $item->id, 'delta' => $delta,
                'reason' => trim($reason), 'performed_by' => $actor->id,
            ]);
        }, 3);
    }
}
