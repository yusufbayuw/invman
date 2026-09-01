<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G002M015ItemInstance;
use App\Models\G005M009ItemReservation;
use App\Models\G005M016ItemReservationDetail;
use Illuminate\Validation\ValidationException;

class ItemReservationAllocationService
{
    public function allocate(G005M009ItemReservation $reservation): void
    {
        $required = max(0, (int) $reservation->quantity);
        $allocatedIds = $reservation->item_reservation_detail()
            ->lockForUpdate()
            ->pluck('g002_m015_item_instance_id')
            ->filter()
            ->values();

        if ($allocatedIds->count() > $required) {
            throw ValidationException::withMessages([
                'status' => 'Alokasi barang satuan melebihi jumlah reservasi. Periksa data detail reservasi.',
            ]);
        }

        if ($allocatedIds->isNotEmpty()) {
            $invalidAllocation = G002M015ItemInstance::query()
                ->whereKey($allocatedIds)
                ->where(function ($query) use ($reservation): void {
                    $query
                        ->where('g002_m007_item_id', '!=', $reservation->g002_m007_item_id)
                        ->orWhere('is_borrowable', false)
                        ->orWhere('is_available', false);
                })
                ->exists();

            if ($invalidAllocation) {
                throw ValidationException::withMessages([
                    'status' => 'Detail reservasi berisi barang satuan yang tidak sesuai atau tidak siap diserahkan.',
                ]);
            }
        }

        $remaining = $required - $allocatedIds->count();

        if ($remaining > 0) {
            $instances = G002M015ItemInstance::query()
                ->where('g002_m007_item_id', $reservation->g002_m007_item_id)
                ->where('is_borrowable', true)
                ->where('is_available', true)
                ->whereNotIn('id', $allocatedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($remaining)
                ->get();

            if ($instances->count() !== $remaining) {
                throw ValidationException::withMessages([
                    'status' => "Barang satuan siap serah hanya {$instances->count()} dari {$remaining} yang masih dibutuhkan.",
                ]);
            }

            foreach ($instances as $instance) {
                G005M016ItemReservationDetail::query()->firstOrCreate([
                    'g005_m009_item_reservation_id' => $reservation->id,
                    'g002_m015_item_instance_id' => $instance->id,
                ]);
                $allocatedIds->push($instance->id);
            }
        }

        G002M015ItemInstance::query()
            ->whereKey($allocatedIds)
            ->lockForUpdate()
            ->get()
            ->each(fn (G002M015ItemInstance $instance) => $instance->update([
                'status' => ReservationStatus::CheckedOut->value,
                'is_available' => false,
            ]));
    }

    public function release(G005M009ItemReservation $reservation, bool $conditionIsGood): void
    {
        $conditions = $reservation->item_reservation_detail()
            ->pluck('g002_m015_item_instance_id')
            ->filter()
            ->mapWithKeys(fn ($id): array => [(int) $id => $conditionIsGood])
            ->all();

        $this->releaseByInstance($reservation, $conditions);
    }

    /** @param array<int, bool> $conditions */
    public function releaseByInstance(G005M009ItemReservation $reservation, array $conditions): void
    {
        $instanceIds = $reservation->item_reservation_detail()
            ->pluck('g002_m015_item_instance_id')
            ->filter();

        G002M015ItemInstance::query()
            ->whereKey($instanceIds)
            ->lockForUpdate()
            ->get()
            ->each(function (G002M015ItemInstance $instance) use ($conditions): void {
                $conditionIsGood = (bool) ($conditions[$instance->id] ?? false);
                $borrowable = $conditionIsGood && (bool) $instance->is_borrowable;

                $instance->update([
                    'status' => $borrowable ? 'tersedia' : 'perlu_perbaikan',
                    'is_available' => $borrowable,
                    'is_borrowable' => $borrowable,
                ]);
            });
    }
}
