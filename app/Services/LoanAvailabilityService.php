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
        $options = [];
        $safety = app(TicketAssetSafetyService::class);

        // Eager-load inventory counts and check safety holds in batches.
        // Never perform one or two hold queries for *each* item row.
        G002M007Item::query()
            ->where('is_borrowable', true)
            ->with('unit')
            ->withCount([
                'item_instance',
                'item_instance as borrowable_instance_count' => fn (Builder $query) => $query->where('is_borrowable', true),
            ])
            ->orderBy('name')->orderBy('id')
            ->chunk(200, function ($items) use (&$options, $start, $end, $safety): void {
                $ids = $items->modelKeys();
                $reserved = $this->peakReservedItemQuantities($ids, $start, $end);
                $blocked = array_fill_keys(array_merge($safety->blockedAssetIds('item', $ids),
                    app(RecurringAssetScheduleService::class)->blockedIds('item', $ids, $start, $end)), true);
                $blockedQuantities = $safety->blockedBorrowableItemQuantities($ids);

                foreach ($items as $item) {
                    if (isset($blocked[$item->id])) {
                        continue;
                    }

                    $stock = $item->item_instance_count > 0
                        ? (int) $item->borrowable_instance_count
                        : (int) ($item->quantity ?? 0);
                    $available = max(0, $stock
                        - (int) ($blockedQuantities[$item->id] ?? 0)
                        - (int) ($reserved[$item->id] ?? 0));

                    if ($available < 1) {
                        continue;
                    }

                    $unit = $item->unit?->name ? " · {$item->unit->name}" : '';
                    $options[$item->id] = "{$item->name}{$unit} · {$available} tersedia";
                }
            });

        return $options;
    }

    public function roomOptions(Carbon|string|null $startTime, Carbon|string|null $endTime): array
    {
        if (! $period = $this->period($startTime, $endTime)) {
            return [];
        }

        [$start, $end] = $period;
        $options = [];
        $safety = app(TicketAssetSafetyService::class);

        G003M006Room::query()
            ->where('is_borrowable', true)
            ->whereDoesntHave('room_reservation', fn (Builder $query) => $this->overlap($query, $start, $end))
            ->with(['floor.building', 'unit'])
            ->orderBy('name')->orderBy('id')
            ->chunk(200, function ($rooms) use (&$options, $safety, $start, $end): void {
                $blocked = array_fill_keys(array_merge($safety->blockedAssetIds('room', $rooms->modelKeys()),
                    app(RecurringAssetScheduleService::class)->blockedIds('room', $rooms->modelKeys(), $start, $end)), true);

                foreach ($rooms as $room) {
                    if (isset($blocked[$room->id])) {
                        continue;
                    }

                    $location = collect([
                        $room->floor?->building?->name,
                        $room->floor?->name,
                    ])->filter()->implode(' · ');

                    $options[$room->id] = trim("{$room->name} · {$location}", ' ·');
                }
            });

        return $options;
    }

    public function vehicleOptions(Carbon|string|null $startTime, Carbon|string|null $endTime): array
    {
        if (! $period = $this->period($startTime, $endTime)) {
            return [];
        }

        [$start, $end] = $period;
        $options = [];
        $safety = app(TicketAssetSafetyService::class);

        G008M017Vehicle::query()
            ->where('is_borrowable', true)
            ->whereDoesntHave('vehicle_reservation', fn (Builder $query) => $this->overlap($query, $start, $end))
            ->with('unit')
            ->orderBy('name')->orderBy('id')
            ->chunk(200, function ($vehicles) use (&$options, $safety, $start, $end): void {
                $blocked = array_fill_keys(array_merge($safety->blockedAssetIds('vehicle', $vehicles->modelKeys()),
                    app(RecurringAssetScheduleService::class)->blockedIds('vehicle', $vehicles->modelKeys(), $start, $end)), true);

                foreach ($vehicles as $vehicle) {
                    if (isset($blocked[$vehicle->id])) {
                        continue;
                    }

                    $plate = $vehicle->license_plate ? " · {$vehicle->license_plate}" : '';
                    $options[$vehicle->id] = "{$vehicle->name}{$plate}";
                }
            });

        return $options;
    }

    public function reservedItemQuantity(int $itemId, Carbon $start, Carbon $end, bool $locking = false): int
    {
        return (int) ($this->peakReservedItemQuantities([$itemId], $start, $end, $locking)[$itemId] ?? 0);
    }

    /**
     * Peak simultaneous quantity in the requested half-open interval [start, end).
     *
     * Adding every reservation that touches a long interval is too restrictive:
     * three projectors at 09-10 and three at 11-12 only need three units, not six.
     * Every matching row is inspected in one batch; exits at the same timestamp
     * cancel starts, so adjacent bookings never count as overlapping.
     *
     * Unreturned, overdue checkouts occupy stock for every future interval.
     * An on-time checkout occupies it until its scheduled return, enabling
     * a *future reservation* afterwards; physical checkout still requires
     * actually available item instances at handover.
     *
     * Locking reads are mandatory for submitDraft() inside the transaction.
     * They preserve current-read semantics of the parent activity subquery
     * under MySQL REPEATABLE READ, preventing oversubscription.
     *
     * @param list<int> $itemIds
     * @return array<int, int>
     */
    private function peakReservedItemQuantities(array $itemIds, Carbon $start, Carbon $end, bool $locking = false): array
    {
        if ($itemIds === [] || ! $end->greaterThan($start)) {
            return [];
        }

        $query = G005M009ItemReservation::query()
            ->whereIn('g002_m007_item_id', $itemIds)
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end, $locking));

        if ($locking) {
            $query->lockForUpdate();
        }

        $now = now();
        $permanent = [];
        $events = [];
        foreach ($query->get(['g002_m007_item_id', 'quantity', 'status', 'start_time', 'end_time']) as $reservation) {
            $itemId = (int) $reservation->g002_m007_item_id;
            $quantity = max(0, (int) $reservation->quantity);
            if ($quantity === 0) {
                continue;
            }

            $outstanding = in_array($reservation->status, [
                ReservationStatus::CheckedOut->value,
                ReservationStatus::ReturnRequested->value,
            ], true);

            // A late physical return cannot be promised for any future booking.
            if ($outstanding && (! $reservation->end_time || $reservation->end_time->lessThanOrEqualTo($now))) {
                $permanent[$itemId] = ($permanent[$itemId] ?? 0) + $quantity;

                continue;
            }

            $windowStart = $reservation->start_time;
            $windowEnd = $reservation->end_time;
            if (! $windowStart || ! $windowEnd) {
                continue;
            }

            // Early handover means the equipment is physically out already.
            if ($outstanding && $windowStart->greaterThan($now)) {
                $windowStart = $now;
            }

            $left = $windowStart->greaterThan($start) ? $windowStart : $start;
            $right = $windowEnd->lessThan($end) ? $windowEnd : $end;
            if (! $right->greaterThan($left)) {
                continue;
            }

            $starts = $left->getTimestamp();
            $ends = $right->getTimestamp();
            $events[$itemId][$starts] = ($events[$itemId][$starts] ?? 0) + $quantity;
            $events[$itemId][$ends] = ($events[$itemId][$ends] ?? 0) - $quantity;
        }

        $peaks = [];
        foreach ($itemIds as $id) {
            $timeline = $events[$id] ?? [];
            ksort($timeline, SORT_NUMERIC);
            $running = 0;
            $peak = 0;
            foreach ($timeline as $change) {
                $running += $change;
                $peak = max($peak, $running);
            }

            $peaks[$id] = ($permanent[$id] ?? 0) + $peak;
        }

        return $peaks;
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

        $safety = app(TicketAssetSafetyService::class);
        if ($safety->isBlocked('item', (int) $item->id)
            || app(RecurringAssetScheduleService::class)->isBlocked('item', (int) $item->id, ...$period)) {
            return 0;
        }
        [$start, $end] = $period;
        $stock = $item->item_instance_count > 0
            ? (int) $item->borrowable_instance_count
            : (int) ($item->quantity ?? 0);

        return max(0, $stock - $safety->blockedItemQuantity((int) $item->id)
            - $this->reservedItemQuantity((int) $item->id, $start, $end, $locking));
    }

    public function roomIsAvailable(int $roomId, Carbon $start, Carbon $end, bool $locking = false): bool
    {
        if (app(TicketAssetSafetyService::class)->isBlocked('room', $roomId)
            || app(RecurringAssetScheduleService::class)->isBlocked('room', $roomId, $start, $end)) {
            return false;
        }
        $query = G005M010RoomReservation::query()
            ->where('g003_m006_room_id', $roomId)
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end, $locking));

        return $locking ? $query->lockForUpdate()->first(['id']) === null : ! $query->exists();
    }

    public function vehicleIsAvailable(int $vehicleId, Carbon $start, Carbon $end, bool $locking = false): bool
    {
        if (app(TicketAssetSafetyService::class)->isBlocked('vehicle', $vehicleId)
            || app(RecurringAssetScheduleService::class)->isBlocked('vehicle', $vehicleId, $start, $end)) {
            return false;
        }
        $query = G005M019VehicleReservation::query()
            ->where('g008_m017_vehicle_id', $vehicleId)
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end, $locking));

        return $locking ? $query->lockForUpdate()->first(['id']) === null : ! $query->exists();
    }

    public function driverIsAvailable(int $driverId, Carbon $start, Carbon $end, ?string $exceptReservationId = null): bool
    {
        return G005M019VehicleReservation::query()
            ->where('g008_m018_driver_id', $driverId)
            ->when($exceptReservationId, fn (Builder $query) => $query->whereKeyNot($exceptReservationId))
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end, true))
            ->lockForUpdate()->first(['id']) === null;
    }

    public function assistantIsAvailable(int $assistantId, Carbon $start, Carbon $end, ?string $exceptReservationId = null): bool
    {
        return G005M019VehicleReservation::query()
            ->where('vehicle_assistant_id', $assistantId)
            ->when($exceptReservationId, fn (Builder $query) => $query->whereKeyNot($exceptReservationId))
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end, true))
            ->lockForUpdate()->first(['id']) === null;
    }

    private function overlap(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query
            ->where(fn (Builder $query) => $this->applyReservationWindow($query, $start, $end));
    }

    private function applyReservationWindow(Builder $query, Carbon $start, Carbon $end, bool $locking = false): void
    {
        $query
            ->where(function (Builder $query) use ($end, $start, $locking): void {
                $query
                    ->where(fn (Builder $query) => $this->applyBlockingScope($query, $locking))
                    ->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->orWhereIn('status', [
                ReservationStatus::CheckedOut->value,
                ReservationStatus::ReturnRequested->value,
            ]);
    }

    public function applyBlockingScope(Builder $query, bool $locking = false): void
    {
        $query
            ->whereIn('status', ReservationStatus::confirmedBlockingValues())
            ->orWhere(function (Builder $query) use ($locking): void {
                $query
                    ->where('status', ReservationStatus::Submitted->value)
                    ->whereHas('activity', function (Builder $query) use ($locking): void {
                        $query
                            ->where('status', ReservationStatus::Submitted->value)
                            ->where('hold_strategy', LoanSettings::HOLD_ON_SUBMISSION)
                            ->where(function (Builder $query): void {
                                $query
                                    ->whereNull('hold_expires_at')
                                    ->orWhere('hold_expires_at', '>', now());
                            })
                            // FOR UPDATE on the outer query does not make an
                            // EXISTS subquery a current read in MySQL.
                            ->when($locking, fn (Builder $query) => $query->lockForUpdate());
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
