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
use App\Models\LoanRequestChecklist;
use App\Models\LoanReservationStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanRequestService
{
    public function __construct(
        private readonly LoanAvailabilityService $availability,
        private readonly LoanNotificationService $notifications,
        private readonly ItemReservationAllocationService $itemAllocations,
    ) {}

    public function submit(User $user, array $data): G004M008Activity
    {
        $draft = $this->saveDraft($user, $data);

        return $this->submitDraft($user, $draft);
    }

    public function saveDraft(User $user, array $data, ?G004M008Activity $activity = null): G004M008Activity
    {
        if (! $user->g001_m001_unit_id) {
            throw ValidationException::withMessages([
                'data.requester' => 'Akun Anda belum terhubung ke unit. Hubungi admin sebelum mengajukan peminjaman.',
            ]);
        }

        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        if ($start->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'data.start_time' => 'Waktu mulai harus berada di masa mendatang.',
            ]);
        }

        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                'data.end_time' => 'Waktu selesai harus setelah waktu mulai.',
            ]);
        }

        $this->validateDistinctNeeds($data['needs'] ?? []);
        if ($activity && (! $user->belongsToUnit($activity->g001_m001_unit_id) || $activity->status !== ReservationStatus::Draft->value)) {
            throw ValidationException::withMessages(['status' => 'Hanya draf milik unit Anda yang dapat diubah.']);
        }

        return DB::transaction(function () use ($user, $data, $start, $end, $activity) {
            $attributes = [
                'user_id' => $user->id,
                'g001_m001_unit_id' => $user->g001_m001_unit_id,
                'name' => $data['name'],
                'description' => $data['description'],
                'notes' => $data['notes'] ?? null,
                'start_time' => $start,
                'end_time' => $end,
                'attachment' => $data['attachment'] ?? null,
                'status' => ReservationStatus::Draft->value,
                'hold_expires_at' => null,
            ];

            if ($activity) {
                $activity->update($attributes);
                $activity->item_reservation()->delete();
                $activity->room_reservation()->delete();
                $activity->vehicle_reservation()->delete();
            } else {
                $activity = G004M008Activity::query()->create($attributes);
            }

            foreach (array_values($data['needs'] ?? []) as $index => $need) {
                match ($need['type'] ?? null) {
                    'item' => $this->createItemReservation($activity, $need, $index, $start, $end, ReservationStatus::Draft),
                    'room' => $this->createRoomReservation($activity, $need, $index, $start, $end, ReservationStatus::Draft),
                    'vehicle' => $this->createVehicleReservation($activity, $need, $index, $start, $end, ReservationStatus::Draft),
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

    public function submitDraft(User $user, G004M008Activity $activity): G004M008Activity
    {
        if (! $user->belongsToUnit($activity->g001_m001_unit_id) || $activity->status !== ReservationStatus::Draft->value) {
            throw ValidationException::withMessages(['status' => 'Hanya draf milik unit Anda yang dapat diajukan.']);
        }

        $this->validateDraftAvailability($activity);
        $holdExpiresAt = $this->holdExpiresAt($activity->start_time);

        DB::transaction(function () use ($user, $activity, $holdExpiresAt) {
            $activity->update([
                'status' => ReservationStatus::Submitted->value,
                'hold_expires_at' => $holdExpiresAt,
            ]);
            $this->transitionReservations($activity, [ReservationStatus::Draft], ReservationStatus::Submitted, $user);
        }, 3);

        $fresh = $activity->fresh();
        $this->notifications->submitted($fresh);

        return $fresh;
    }

    public function cancel(G004M008Activity $activity): void
    {
        $user = auth()->user();

        if (! $user?->belongsToUnit($activity->g001_m001_unit_id)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya pemohon dari unit terkait yang dapat membatalkan pengajuan.',
            ]);
        }

        if ($activity->status !== ReservationStatus::Submitted->value) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan hanya dapat dibatalkan selama masih menunggu persetujuan.',
            ]);
        }

        $previousStatus = ReservationStatus::tryFrom($activity->status) ?? ReservationStatus::Submitted;

        DB::transaction(function () use ($activity, $user) {
            $activity->update([
                'status' => ReservationStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);
            $this->transitionReservations($activity, ReservationStatus::cases(), ReservationStatus::Cancelled, $user);
        });

        $this->notifications->statusChanged($activity->fresh(), $previousStatus, ReservationStatus::Cancelled);
    }

    public function requestReturn(G004M008Activity $activity, ?array $checklistData = null): void
    {
        $user = auth()->user();

        if (! $user?->belongsToUnit($activity->g001_m001_unit_id)) {
            throw ValidationException::withMessages(['status' => 'Hanya unit pemohon yang dapat mengajukan pengembalian.']);
        }

        $changed = DB::transaction(function () use ($activity, $checklistData, $user): int {
            $locked = G004M008Activity::query()->lockForUpdate()->findOrFail($activity->getKey());

            if ($checklistData !== null) {
                $conditionIsGood = (bool) ($checklistData['is_ok'] ?? false);

                if (! $conditionIsGood && blank($checklistData['notes'] ?? null)) {
                    throw ValidationException::withMessages([
                        'checklist.notes' => 'Catatan kerusakan atau masalah wajib diisi jika kondisi aset tidak baik.',
                    ]);
                }

                LoanRequestChecklist::query()->updateOrCreate(
                    ['g004_m008_activity_id' => $locked->id, 'stage' => 'return'],
                    [
                        'user_id' => $user->id,
                        'is_ok' => $conditionIsGood,
                        'notes' => $checklistData['notes'] ?? null,
                        'photo' => $checklistData['photo'] ?? null,
                    ],
                );
            }

            if (! $locked->return_checklist()->exists()) {
                throw ValidationException::withMessages([
                    'checklist' => 'Checklist kondisi aset wajib diisi sebelum mengajukan pengembalian.',
                ]);
            }

            $count = $this->transitionReservations(
                $locked,
                [ReservationStatus::CheckedOut],
                ReservationStatus::ReturnRequested,
                $user,
            );
            $this->syncStatus($locked->fresh(), notify: false);

            return $count;
        }, 3);

        if ($changed < 1) {
            throw ValidationException::withMessages(['status' => 'Tidak ada kebutuhan yang sedang dipakai untuk diajukan pengembaliannya.']);
        }
    }

    public function processReservation(
        string $type,
        string $reservationId,
        ReservationStatus $status,
        ?string $rejectionReason = null,
    ): void {
        $modelClass = match ($type) {
            'item' => G005M009ItemReservation::class,
            'room' => G005M010RoomReservation::class,
            'vehicle' => G005M019VehicleReservation::class,
            default => throw ValidationException::withMessages(['status' => 'Jenis kebutuhan tidak valid.']),
        };

        $activityId = $modelClass::query()
            ->whereKey($reservationId)
            ->value('g004_m008_activity_id');

        $name = DB::transaction(function () use ($activityId, $modelClass, $rejectionReason, $reservationId, $status, $type): string {
            if ($activityId) {
                G004M008Activity::query()->lockForUpdate()->findOrFail($activityId);
            }

            $reservation = $modelClass::query()->lockForUpdate()->findOrFail($reservationId);

            if ($activityId && $reservation->g004_m008_activity_id !== $activityId) {
                throw ValidationException::withMessages([
                    'status' => 'Data pengajuan berubah saat diproses. Muat ulang halaman dan coba kembali.',
                ]);
            }

            if (! auth()->user()?->managesReservation($reservation)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya user yang ditetapkan pada Pengelola Barang aset ini yang dapat memproses peminjaman.',
                ]);
            }

            $reservation->status = $status->value;

            if ($reservation instanceof G005M009ItemReservation && $status === ReservationStatus::CheckedOut) {
                $this->itemAllocations->allocate($reservation);
            }

            if ($status === ReservationStatus::Rejected) {
                $reservation->rejection_reason = $rejectionReason;
            }

            if ($status === ReservationStatus::Returned) {
                $reservation->returned_at = now();
            }

            $reservation->save();

            if ($status === ReservationStatus::Returned) {
                $conditionIsGood = (bool) $reservation->activity?->return_checklist?->is_ok;

                if ($reservation instanceof G005M009ItemReservation) {
                    $this->itemAllocations->release($reservation, $conditionIsGood);
                } elseif (! $conditionIsGood) {
                    $asset = match (true) {
                        $reservation instanceof G005M010RoomReservation => $reservation->room,
                        $reservation instanceof G005M019VehicleReservation => $reservation->vehicle,
                    };

                    $asset?->update([
                        'is_borrowable' => false,
                        'status' => 'perlu_perbaikan',
                    ]);
                }
            }

            return match ($type) {
                'item' => $reservation->item?->name ?? 'barang',
                'room' => $reservation->room?->name ?? 'ruangan',
                'vehicle' => $reservation->vehicle?->name ?? 'kendaraan',
            };
        }, 3);

        $this->notifications->sendStatusToast($status, $name);
    }

    public function syncStatus(G004M008Activity $activity, bool $notify = true): void
    {
        if (in_array($activity->status, [
            ReservationStatus::Draft->value,
            ReservationStatus::Cancelled->value,
            ReservationStatus::Expired->value,
        ], true)) {
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

        $hasPending = $statuses->contains(ReservationStatus::Submitted->value)
            || $statuses->contains(ReservationStatus::Draft->value);
        $hasFulfilledNeed = $statuses->contains(ReservationStatus::Approved->value)
            || $statuses->contains(ReservationStatus::Returned->value);
        $hasUnavailableNeed = $statuses->contains(fn (string $value): bool => in_array($value, [
            ReservationStatus::Rejected->value,
            ReservationStatus::Cancelled->value,
            ReservationStatus::Expired->value,
        ], true));

        $status = match (true) {
            $statuses->contains(ReservationStatus::CheckedOut->value) => ReservationStatus::CheckedOut,
            $hasPending => ReservationStatus::Submitted,
            $statuses->contains(ReservationStatus::ReturnRequested->value) => ReservationStatus::ReturnRequested,
            $hasFulfilledNeed && $hasUnavailableNeed => ReservationStatus::PartiallyApproved,
            $statuses->every(fn (string $value): bool => $value === ReservationStatus::Returned->value) => ReservationStatus::Returned,
            $statuses->contains(ReservationStatus::Approved->value) => ReservationStatus::Approved,
            $statuses->every(fn (string $value): bool => $value === ReservationStatus::Rejected->value) => ReservationStatus::Rejected,
            $statuses->every(fn (string $value): bool => $value === ReservationStatus::Expired->value) => ReservationStatus::Expired,
            default => ReservationStatus::Cancelled,
        };

        $previousStatus = ReservationStatus::tryFrom($activity->status) ?? ReservationStatus::Submitted;

        if ($previousStatus === $status) {
            return;
        }

        $activity->updateQuietly(['status' => $status->value]);
        if ($notify) {
            $this->notifications->statusChanged($activity->fresh(), $previousStatus, $status);
        }
    }

    public function expireStaleHolds(): int
    {
        $expired = 0;

        G004M008Activity::query()
            ->where('status', ReservationStatus::Submitted->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($activities) use (&$expired): void {
                foreach ($activities as $activity) {
                    if ($this->expirePendingHold($activity)) {
                        $expired++;
                    }
                }
            });

        return $expired;
    }

    public function expirePendingHold(G004M008Activity $activity): bool
    {
        $previousStatus = ReservationStatus::tryFrom($activity->status) ?? ReservationStatus::Submitted;
        $changed = DB::transaction(function () use ($activity): bool {
            $locked = G004M008Activity::query()->lockForUpdate()->find($activity->getKey());

            if (! $locked
                || $locked->status !== ReservationStatus::Submitted->value
                || ! $locked->hold_expires_at
                || $locked->hold_expires_at->isFuture()) {
                return false;
            }

            $affected = $this->transitionReservations(
                $locked,
                [ReservationStatus::Draft, ReservationStatus::Submitted],
                ReservationStatus::Expired,
            );

            if ($affected < 1) {
                return false;
            }

            $locked->updateQuietly(['expired_at' => now()]);
            $this->syncStatus($locked, notify: false);

            return true;
        }, 3);

        if ($changed) {
            $fresh = $activity->fresh();
            $newStatus = ReservationStatus::tryFrom($fresh->status) ?? ReservationStatus::Expired;
            $this->notifications->holdExpired($fresh, $previousStatus, $newStatus);
        }

        return $changed;
    }

    public function assertDecisionAllowed(Model $reservation): void
    {
        if ($reservation->getOriginal('status') !== ReservationStatus::Submitted->value) {
            throw ValidationException::withMessages([
                'status' => 'Hanya kebutuhan yang masih menunggu persetujuan yang dapat diputuskan.',
            ]);
        }

        $activity = $reservation->activity;

        if (! $activity || ($activity->hold_expires_at && $activity->hold_expires_at->lessThanOrEqualTo(now()))) {
            throw ValidationException::withMessages([
                'status' => 'Masa tahan pengajuan telah berakhir. Muat ulang halaman untuk melihat status terbaru.',
            ]);
        }
    }

    /**
     * Ensures reservation status changes follow the role-based loan workflow.
     *
     * Pemohon submits/cancels/requests a return. The assigned asset manager
     * decides, hands over, and confirms the return of each requested need.
     */
    public function assertReservationTransitionAllowed(Model $reservation): void
    {
        if (! $reservation->isDirty('status')) {
            return;
        }

        $from = ReservationStatus::tryFrom($reservation->getOriginal('status'));
        $to = ReservationStatus::tryFrom($reservation->status);

        if (! $from || ! $to || $from === $to) {
            return;
        }

        $user = auth()->user();

        if ($to === ReservationStatus::ReturnRequested) {
            if (! $user?->belongsToUnit($reservation->activity?->g001_m001_unit_id)) {
                throw ValidationException::withMessages(['status' => 'Hanya unit pemohon yang dapat mengajukan pengembalian.']);
            }

            $this->assertTransition($from, ReservationStatus::CheckedOut, 'Pengembalian hanya dapat diajukan saat kebutuhan sedang dipakai.');

            return;
        }

        if (! $user?->managesReservation($reservation)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya user yang ditetapkan pada Pengelola Barang aset ini yang dapat memproses status peminjaman.',
            ]);
        }

        match ($to) {
            ReservationStatus::Approved,
            ReservationStatus::Rejected => $this->assertDecisionAllowed($reservation),
            ReservationStatus::CheckedOut => $this->assertTransition(
                $from,
                ReservationStatus::Approved,
                'Kebutuhan hanya dapat diserahkan setelah disetujui.',
            ),
            ReservationStatus::Returned => $this->assertTransition(
                $from,
                ReservationStatus::ReturnRequested,
                'Kebutuhan hanya dapat dikonfirmasi kembali setelah diajukan oleh pemohon.',
            ),
            default => throw ValidationException::withMessages([
                'status' => 'Perubahan status ini tidak dapat dilakukan secara manual.',
            ]),
        };

        if (in_array($to, [ReservationStatus::Approved, ReservationStatus::Rejected], true)) {
            if ($to === ReservationStatus::Rejected && blank($reservation->rejection_reason)) {
                throw ValidationException::withMessages([
                    'rejection_reason' => 'Alasan penolakan wajib diisi.',
                ]);
            }

            $reservation->decision_by = $user->id;
            $reservation->decision_at = now();

            if ($to === ReservationStatus::Approved) {
                $reservation->rejection_reason = null;
            }
        }

        $reservation->status_changed_by = $user->id;
        $reservation->status_changed_at = now();
    }

    public function recordStatusHistory(Model $reservation): void
    {
        if (! $reservation->wasChanged('status')) {
            return;
        }

        $type = match (true) {
            $reservation instanceof G005M009ItemReservation => 'item',
            $reservation instanceof G005M010RoomReservation => 'room',
            $reservation instanceof G005M019VehicleReservation => 'vehicle',
            default => null,
        };

        if (! $type) {
            return;
        }

        $this->createStatusHistory(
            $reservation,
            $reservation->getOriginal('status'),
            $reservation->status,
            $reservation->status_changed_by,
            $reservation->status === ReservationStatus::Rejected->value ? $reservation->rejection_reason : null,
        );
    }

    private function createStatusHistory(
        Model $reservation,
        ?string $from,
        string $to,
        ?int $changedBy = null,
        ?string $notes = null,
    ): void {
        $type = match (true) {
            $reservation instanceof G005M009ItemReservation => 'item',
            $reservation instanceof G005M010RoomReservation => 'room',
            $reservation instanceof G005M019VehicleReservation => 'vehicle',
            default => null,
        };

        if (! $type) {
            return;
        }

        LoanReservationStatusHistory::query()->create([
            'reservation_type' => $type,
            'reservation_id' => $reservation->getKey(),
            'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $changedBy,
            'notes' => $notes,
        ]);
    }

    /** @param array<int, ReservationStatus> $from */
    private function transitionReservations(
        G004M008Activity $activity,
        array $from,
        ReservationStatus $to,
        ?User $user = null,
    ): int {
        $fromValues = collect($from)->map(fn (ReservationStatus $status): string => $status->value);
        $changed = 0;

        foreach (['item_reservation', 'room_reservation', 'vehicle_reservation'] as $relation) {
            foreach ($activity->{$relation}()->whereIn('status', $fromValues)->get() as $reservation) {
                $previous = $reservation->status;
                $reservation->updateQuietly([
                    'status' => $to->value,
                    'status_changed_by' => $user?->id,
                    'status_changed_at' => now(),
                ]);
                $this->createStatusHistory($reservation, $previous, $to->value, $user?->id);
                $changed++;
            }
        }

        return $changed;
    }

    private function assertTransition(
        ReservationStatus $from,
        ReservationStatus $expectedFrom,
        string $message,
    ): void {
        if ($from !== $expectedFrom) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function createItemReservation(G004M008Activity $activity, array $need, int $index, Carbon $start, Carbon $end, ReservationStatus $status): void
    {
        $itemId = (int) ($need['item_id'] ?? 0);
        $quantity = (int) ($need['quantity'] ?? 0);
        $item = G002M007Item::query()->lockForUpdate()->find($itemId);

        if (! $item || ! $item->is_borrowable || $quantity < 1) {
            throw ValidationException::withMessages([
                "data.needs.{$index}.item_id" => 'Barang tidak valid atau tidak dapat dipinjam.',
            ]);
        }

        $available = $this->availability->availableItemQuantity($itemId, $start, $end);

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
            'status' => $status->value,
        ]);
    }

    private function createRoomReservation(G004M008Activity $activity, array $need, int $index, Carbon $start, Carbon $end, ReservationStatus $status): void
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
            'status' => $status->value,
        ]);
    }

    private function validateDistinctNeeds(array $needs): void
    {
        $fields = [
            'item' => 'item_id',
            'room' => 'room_id',
            'vehicle' => 'vehicle_id',
        ];
        $selected = [];

        foreach (array_values($needs) as $index => $need) {
            $type = $need['type'] ?? null;
            $field = $fields[$type] ?? null;
            $id = $field ? ($need[$field] ?? null) : null;

            if (! $field || blank($id)) {
                continue;
            }

            $key = "{$type}:{$id}";

            if (isset($selected[$key])) {
                throw ValidationException::withMessages([
                    "data.needs.{$index}.{$field}" => 'Pilihan ini sudah ditambahkan pada baris sebelumnya.',
                ]);
            }

            $selected[$key] = true;
        }
    }

    private function createVehicleReservation(G004M008Activity $activity, array $need, int $index, Carbon $start, Carbon $end, ReservationStatus $status): void
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
            'status' => $status->value,
        ]);
    }

    private function holdExpiresAt(Carbon $start): Carbon
    {
        $deadline = now()->addHours(app(LoanSettings::class)->holdHours());

        return $start->lessThan($deadline) ? $start : $deadline;
    }

    private function validateDraftAvailability(G004M008Activity $activity): void
    {
        foreach ($activity->item_reservation as $reservation) {
            if ($this->availability->availableItemQuantity($reservation->g002_m007_item_id, $activity->start_time, $activity->end_time) < $reservation->quantity) {
                throw ValidationException::withMessages(['status' => "Stok {$reservation->item?->name} tidak lagi mencukupi untuk jadwal ini."]);
            }
        }

        foreach ($activity->room_reservation as $reservation) {
            if (! $this->availability->roomIsAvailable($reservation->g003_m006_room_id, $activity->start_time, $activity->end_time)) {
                throw ValidationException::withMessages(['status' => "{$reservation->room?->name} tidak lagi tersedia untuk jadwal ini."]);
            }
        }

        foreach ($activity->vehicle_reservation as $reservation) {
            if (! $this->availability->vehicleIsAvailable($reservation->g008_m017_vehicle_id, $activity->start_time, $activity->end_time)) {
                throw ValidationException::withMessages(['status' => "{$reservation->vehicle?->name} tidak lagi tersedia untuk jadwal ini."]);
            }
        }
    }
}
