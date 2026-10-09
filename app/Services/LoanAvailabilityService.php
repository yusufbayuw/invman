<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class LoanAvailabilityService
{
    public function itemOptions(Carbon|string|null $startTime, Carbon|string|null $endTime): array
    {
        if (! $period = $this->period($startTime, $endTime)) {
            return [];
        }

        [$start, $end] = $period;
        $reserved = G005M009ItemReservation::query()
            ->selectRaw('g002_m007_item_id, COALESCE(SUM(quantity), 0) as reserved_quantity')
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end))
            ->groupBy('g002_m007_item_id')
            ->pluck('reserved_quantity', 'g002_m007_item_id');

        return G002M007Item::query()
            ->where('is_borrowable', true)
            ->with('unit')
            ->withCount([
                'item_instance',
                'item_instance as borrowable_instance_count' => fn (Builder $query) => $query->where('is_borrowable', true),
            ])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (G002M007Item $item) use ($reserved) {
                $stock = $item->item_instance_count > 0
                    ? (int) $item->borrowable_instance_count
                    : (int) ($item->quantity ?? 0);
                $available = max(0, $stock - (int) ($reserved[$item->id] ?? 0));

                if ($available < 1) {
                    return [];
                }

                $unit = $item->unit?->name ? " · {$item->unit->name}" : '';

                return [$item->id => "{$item->name}{$unit} · {$available} tersedia"];
            })
            ->all();
    }

    public function roomOptions(Carbon|string|null $startTime, Carbon|string|null $endTime): array
    {
        if (! $period = $this->period($startTime, $endTime)) {
            return [];
        }

        [$start, $end] = $period;

        return G003M006Room::query()
            ->where('is_borrowable', true)
            ->whereDoesntHave('room_reservation', fn (Builder $query) => $this->overlap($query, $start, $end))
            ->with(['floor.building', 'unit'])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (G003M006Room $room) {
                $location = collect([
                    $room->floor?->building?->name,
                    $room->floor?->name,
                ])->filter()->implode(' · ');

                return [$room->id => trim("{$room->name} · {$location}", ' ·')];
            })
            ->all();
    }

    public function vehicleOptions(Carbon|string|null $startTime, Carbon|string|null $endTime): array
    {
        if (! $period = $this->period($startTime, $endTime)) {
            return [];
        }

        [$start, $end] = $period;

        return G008M017Vehicle::query()
            ->where('is_borrowable', true)
            ->whereDoesntHave('vehicle_reservation', fn (Builder $query) => $this->overlap($query, $start, $end))
            ->with('unit')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (G008M017Vehicle $vehicle) {
                $plate = $vehicle->license_plate ? " · {$vehicle->license_plate}" : '';

                return [$vehicle->id => "{$vehicle->name}{$plate}"];
            })
            ->all();
    }

    public function reservedItemQuantity(int $itemId, Carbon $start, Carbon $end, bool $locking = false): int
    {
        $query = G005M009ItemReservation::query()
            ->where('g002_m007_item_id', $itemId)
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end));

        // Only transactional acceptance needs locking reads. UI queries retain
        // the original database-side SUM for constant-memory reporting.
        return $locking
            ? (int) $query->lockForUpdate()->get(['quantity'])->sum('quantity')
            : (int) $query->sum('quantity');
    }

    public function availableItemQuantity(int|string|null $itemId, Carbon|string|null $startTime, Carbon|string|null $endTime, bool $locking = false): int
    {
        if (! $itemId || ! $period = $this->period($startTime, $endTime)) {
            return 0;
        }

        $item = G002M007Item::query()
            ->where('is_borrowable', true)
            ->withCount([
                'item_instance',
                'item_instance as borrowable_instance_count' => fn (Builder $query) => $query->where('is_borrowable', true),
            ])
            ->find($itemId);

        if (! $item) {
            return 0;
        }

        [$start, $end] = $period;
        $stock = $item->item_instance_count > 0
            ? (int) $item->borrowable_instance_count
            : (int) ($item->quantity ?? 0);

        return max(0, $stock - $this->reservedItemQuantity((int) $item->id, $start, $end, $locking));
    }

    public function roomIsAvailable(int $roomId, Carbon $start, Carbon $end, bool $locking = false): bool
    {
        $query = G005M010RoomReservation::query()
            ->where('g003_m006_room_id', $roomId)
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end));

        return $locking ? $query->lockForUpdate()->first(['id']) === null : ! $query->exists();
    }

    public function vehicleIsAvailable(int $vehicleId, Carbon $start, Carbon $end, bool $locking = false): bool
    {
        $query = G005M019VehicleReservation::query()
            ->where('g008_m017_vehicle_id', $vehicleId)
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end));

        return $locking ? $query->lockForUpdate()->first(['id']) === null : ! $query->exists();
    }

    public function driverIsAvailable(int $driverId, Carbon $start, Carbon $end, ?string $exceptReservationId = null): bool
    {
        return G005M019VehicleReservation::query()
            ->where('g008_m018_driver_id', $driverId)
            ->when($exceptReservationId, fn (Builder $query) => $query->whereKeyNot($exceptReservationId))
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end))
            ->lockForUpdate()->first(['id']) === null;
    }

    public function assistantIsAvailable(int $assistantId, Carbon $start, Carbon $end, ?string $exceptReservationId = null): bool
    {
        return G005M019VehicleReservation::query()
            ->where('vehicle_assistant_id', $assistantId)
            ->when($exceptReservationId, fn (Builder $query) => $query->whereKeyNot($exceptReservationId))
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end))
            ->lockForUpdate()->first(['id']) === null;
    }

    private function overlap(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end));
    }

    private function applyReservationWindow(Builder $query, Carbon $start, Carbon $end): void
    {
        $query
            ->where(function (Builder $query) use ($end, $start): void {
                $query
                    ->where(fn (Builder $query) => $this->applyBlockingScope($query))
                    ->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->orWhereIn('status', [
                ReservationStatus::CheckedOut->value,
                ReservationStatus::ReturnRequested->value,
            ]);
    }

    public function applyBlockingScope(Builder $query): void
    {
        $query
            ->whereIn('status', ReservationStatus::confirmedBlockingValues())
            ->orWhere(function (Builder $query): void {
                $query
                    ->where('status', ReservationStatus::Submitted->value)
                    ->whereHas('activity', function (Builder $query): void {
                        $query
                            ->where('status', ReservationStatus::Submitted->value)
                            ->where(function (Builder $query): void {
                                $query
                                    ->whereNull('hold_expires_at')
                                    ->orWhere('hold_expires_at', '>', now());
                            });
                    });
            });
    }

    private function period(Carbon|string|null $startTime, Carbon|string|null $endTime): ?array
    {
        if (! $startTime || ! $endTime) {
            return null;
        }

        try {
            $start = $startTime instanceof Carbon ? $startTime->copy() : Carbon::parse($startTime);
            $end = $endTime instanceof Carbon ? $endTime->copy() : Carbon::parse($endTime);
        } catch (\Throwable) {
            return null;
        }

        return $end->greaterThan($start) ? [$start, $end] : null;
    }
}
