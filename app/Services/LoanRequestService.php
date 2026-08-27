<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G002M007Item;
use App\Models\G003M006Room;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\G008M017Vehicle;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanRequestService
{
    public function __construct(private readonly LoanAvailabilityService $availability)
    {
    }

    public function submit(User $user, array $data): G004M008Activity
    {
        if (! $user->g001_m001_unit_id) {
            throw ValidationException::withMessages([
                'data.requester' => 'Akun Anda belum terhubung ke unit. Hubungi admin sebelum mengajukan peminjaman.',
            ]);
        }

        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                'data.end_time' => 'Waktu selesai harus setelah waktu mulai.',
            ]);
        }

        return DB::transaction(function () use ($user, $data, $start, $end) {
            $activity = G004M008Activity::query()->create([
                'user_id' => $user->id,
                'g001_m001_unit_id' => $user->g001_m001_unit_id,
                'name' => $data['name'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'start_time' => $start,
                'end_time' => $end,
                'attachment' => $data['attachment'] ?? null,
                'status' => ReservationStatus::Submitted->value,
            ]);

            foreach (array_values($data['needs'] ?? []) as $index => $need) {
                match ($need['type'] ?? null) {
                    'item' => $this->createItemReservation($activity, $need, $index, $start, $end),
                    'room' => $this->createRoomReservation($activity, $need, $index, $start, $end),
                    'vehicle' => $this->createVehicleReservation($activity, $need, $index, $start, $end),
                    default => throw ValidationException::withMessages([
                        "data.needs.{$index}.type" => 'Pilih jenis kebutuhan yang valid.',
                    ]),
                };
            }

            return $activity->fresh([
                'item_reservation.item',
                'room_reservation.room',
                'vehicle_reservation.vehicle',
            ]);
        }, 3);
    }

    public function cancel(G004M008Activity $activity): void
    {
        DB::transaction(function () use ($activity) {
            $activity->item_reservation()->update(['status' => ReservationStatus::Cancelled->value]);
            $activity->room_reservation()->update(['status' => ReservationStatus::Cancelled->value]);
            $activity->vehicle_reservation()->update(['status' => ReservationStatus::Cancelled->value]);
            $activity->update([
                'status' => ReservationStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);
        });
    }

    public function syncStatus(G004M008Activity $activity): void
    {
        if ($activity->status === ReservationStatus::Cancelled->value) {
            return;
        }

        $statuses = collect()
            ->concat($activity->item_reservation()->pluck('status'))
            ->concat($activity->room_reservation()->pluck('status'))
            ->concat($activity->vehicle_reservation()->pluck('status'))
            ->filter();

        if ($statuses->isEmpty()) {
            return;
        }

        $status = match (true) {
            $statuses->contains(ReservationStatus::CheckedOut->value) => ReservationStatus::CheckedOut,
            $statuses->contains(ReservationStatus::Submitted->value),
            $statuses->contains(ReservationStatus::Draft->value) => ReservationStatus::Submitted,
            $statuses->contains(ReservationStatus::Approved->value)
                && $statuses->contains(fn (string $value) => in_array($value, [
                    ReservationStatus::Rejected->value,
                    ReservationStatus::Cancelled->value,
                ], true)) => ReservationStatus::PartiallyApproved,
            $statuses->contains(ReservationStatus::Approved->value) => ReservationStatus::Approved,
            $statuses->contains(ReservationStatus::Returned->value) => ReservationStatus::Returned,
            $statuses->every(fn (string $value) => $value === ReservationStatus::Rejected->value) => ReservationStatus::Rejected,
            default => ReservationStatus::Cancelled,
        };

        $activity->updateQuietly(['status' => $status->value]);
    }

    private function createItemReservation(G004M008Activity $activity, array $need, int $index, Carbon $start, Carbon $end): void
    {
        $itemId = (int) ($need['item_id'] ?? 0);
        $quantity = (int) ($need['quantity'] ?? 0);
        $item = G002M007Item::query()->lockForUpdate()->find($itemId);

        if (! $item || ! $item->is_borrowable || $quantity < 1) {
            throw ValidationException::withMessages([
                "data.needs.{$index}.item_id" => 'Barang tidak valid atau tidak dapat dipinjam.',
            ]);
        }

        $stock = (int) ($item->available_quantity ?? $item->quantity ?? 0);
        $available = max(0, $stock - $this->availability->reservedItemQuantity($itemId, $start, $end));

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                "data.needs.{$index}.quantity" => "Stok {$item->name} yang tersedia pada jadwal ini hanya {$available}.",
            ]);
        }

        G005M009ItemReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g002_m007_item_id' => $itemId,
            'quantity' => $quantity,
            'start_time' => $start,
            'end_time' => $end,
            'status' => ReservationStatus::Submitted->value,
        ]);
    }

    private function createRoomReservation(G004M008Activity $activity, array $need, int $index, Carbon $start, Carbon $end): void
    {
        $roomId = (int) ($need['room_id'] ?? 0);
        $room = G003M006Room::query()->lockForUpdate()->find($roomId);

        if (! $room || ! $room->is_borrowable || ! $this->availability->roomIsAvailable($roomId, $start, $end)) {
            throw ValidationException::withMessages([
                "data.needs.{$index}.room_id" => 'Ruangan / tempat tidak tersedia pada jadwal yang dipilih.',
            ]);
        }

        G005M010RoomReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g003_m006_room_id' => $roomId,
            'start_time' => $start,
            'end_time' => $end,
            'status' => ReservationStatus::Submitted->value,
        ]);
    }

    private function createVehicleReservation(G004M008Activity $activity, array $need, int $index, Carbon $start, Carbon $end): void
    {
        $vehicleId = (int) ($need['vehicle_id'] ?? 0);
        $vehicle = G008M017Vehicle::query()->lockForUpdate()->find($vehicleId);

        if (! $vehicle || ! $vehicle->is_borrowable || ! $this->availability->vehicleIsAvailable($vehicleId, $start, $end)) {
            throw ValidationException::withMessages([
                "data.needs.{$index}.vehicle_id" => 'Kendaraan tidak tersedia pada jadwal yang dipilih.',
            ]);
        }

        G005M019VehicleReservation::query()->create([
            'g004_m008_activity_id' => $activity->id,
            'g008_m017_vehicle_id' => $vehicleId,
            'g008_m018_driver_id' => null,
            'start_time' => $start,
            'end_time' => $end,
            'status' => ReservationStatus::Submitted->value,
        ]);
    }
}
