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
use App\Models\LoanHandoverReceipt;
use App\Models\LoanRequestChecklist;
use App\Models\LoanReservationChecklist;
use App\Models\LoanReservationCorrection;
use App\Models\LoanReservationStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $draft = $this->saveDraft($user, $data, validateAvailability: true);

        return $this->submitDraft($user, $draft);
    }

    public function saveDraft(
        User $user,
        array $data,
        ?G004M008Activity $activity = null,
        bool $validateAvailability = false,
    ): G004M008Activity {
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

        // Never accept a cross-unit or nested activity reference, even from a
        // forged Livewire payload or a direct service invocation.
        $relatedActivity = filled($data['related_activity_id'] ?? null)
            ? app(LoanActivityGrouping::class)->resolve($user, $data['related_activity_id'])
            : null;
        $event = filled($data['loan_event_id'] ?? null)
            ? app(LoanEventService::class)->resolve($user, $data['loan_event_id'])
            : null;
        if ($relatedActivity && $event && $relatedActivity->loan_event_id !== $event->id) {
            throw ValidationException::withMessages([
                'data.loan_event_id' => 'Referensi kegiatan tidak konsisten.',
            ]);
        }

        $eventId = $event?->id ?: $relatedActivity?->loan_event_id ?: $activity?->loan_event_id;

        return DB::transaction(function () use ($user, $data, $start, $end, $activity, $validateAvailability, $relatedActivity, $eventId) {
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

            if ($relatedActivity) {
                $attributes['related_activity_id'] = $relatedActivity->getKey();
            }

            if ($activity) {
                if ($eventId) {
                    $activity->forceFill(['loan_event_id' => $eventId]);
                }
                $activity->fill($attributes)->save();
                $activity->item_reservation()->delete();
                $activity->room_reservation()->delete();
                $activity->vehicle_reservation()->delete();
            } else {
                $activity = new G004M008Activity($attributes);
                // Do not put loan_event_id in the public Eloquent fillable list.
                if ($eventId) {
                    $activity->loan_event_id = $eventId;
                }
                $activity->save();
            }

            foreach (array_values($data['needs'] ?? []) as $index => $need) {
                match ($need['type'] ?? null) {
                    'item' => $this->createItemReservation($activity, $need, $index, $start, $end, ReservationStatus::Draft, $validateAvailability),
                    'room' => $this->createRoomReservation($activity, $need, $index, $start, $end, ReservationStatus::Draft, $validateAvailability),
                    'vehicle' => $this->createVehicleReservation($activity, $need, $index, $start, $end, ReservationStatus::Draft, $validateAvailability),
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
        // Acquire locks and recheck availability in ONE transaction. Drafts do not
        // reserve assets, so a pre-transaction availability check is insufficient.
        DB::transaction(function () use ($user, $activity): void {
            $locked = G004M008Activity::query()->lockForUpdate()->findOrFail($activity->getKey());
            if (! $user->belongsToUnit($locked->g001_m001_unit_id)
                || $locked->status !== ReservationStatus::Draft->value) {
                throw ValidationException::withMessages(['status' => 'Hanya draf milik unit Anda yang dapat diajukan.']);
            }

            // Lock asset rows in a stable order across concurrent submissions.
            // Concurrent requests for any of the same assets now serialize.
            foreach ([
                [G002M007Item::class, $locked->item_reservation()->lockForUpdate()->pluck('g002_m007_item_id')],
                [G003M006Room::class, $locked->room_reservation()->lockForUpdate()->pluck('g003_m006_room_id')],
                [G008M017Vehicle::class, $locked->vehicle_reservation()->lockForUpdate()->pluck('g008_m017_vehicle_id')],
            ] as [$modelClass, $ids]) {
                $ids = $ids->filter()->unique()->sort()->values()->all();
                if ($ids !== []) {
                    $modelClass::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
                }
            }

            $locked->load(['item_reservation.item', 'room_reservation.room', 'vehicle_reservation.vehicle']);
            $this->validateDraftAvailability($locked);

            $locked->update([
                'status' => ReservationStatus::Submitted->value,
                'hold_expires_at' => $this->holdExpiresAt($locked->start_time),
            ]);
            $this->transitionReservations($locked, [ReservationStatus::Draft], ReservationStatus::Submitted, $user);
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

        if ($checklistData === null) {
            throw ValidationException::withMessages([
                'checklist' => 'Checklist kondisi aset wajib diisi sebelum mengajukan pengembalian.',
            ]);
        }

        $this->validateChecklistCondition($checklistData);

        $changed = DB::transaction(function () use ($activity, $checklistData, $user): int {
            LoanRequestChecklist::query()->updateOrCreate(
                ['g004_m008_activity_id' => $activity->id, 'stage' => 'return'],
                [
                    'user_id' => $user->id,
                    'is_ok' => (bool) ($checklistData['is_ok'] ?? false),
                    'notes' => $checklistData['notes'] ?? null,
                    'photo' => $checklistData['photo'] ?? null,
                ],
            );

            $changed = 0;
            foreach ([
                'item' => $activity->item_reservation()->where('status', ReservationStatus::CheckedOut->value)->get(),
                'room' => $activity->room_reservation()->where('status', ReservationStatus::CheckedOut->value)->get(),
                'vehicle' => $activity->vehicle_reservation()->where('status', ReservationStatus::CheckedOut->value)->get(),
            ] as $type => $reservations) {
                foreach ($reservations as $reservation) {
                    $this->requestReservationReturn($type, $reservation->getKey(), $checklistData);
                    $changed++;
                }
            }

            return $changed;
        }, 3);

        if ($changed < 1) {
            throw ValidationException::withMessages(['status' => 'Tidak ada kebutuhan yang sedang dipakai untuk diajukan pengembaliannya.']);
        }
    }

    /**
     * Records a return that is received directly by the assigned asset manager.
     *
     * This is the operational fallback for loans that are still checked out when
     * the borrower returns the asset without first submitting a return request.
     */
    public function completeManagedReturn(string $type, string $reservationId, array $checklistData): void
    {
        $modelClass = $this->reservationModelClass($type);
        $activity = DB::transaction(function () use ($checklistData, $modelClass, $reservationId, $type): G004M008Activity {
            $reservation = $modelClass::query()
                ->with('activity')
                ->lockForUpdate()
                ->findOrFail($reservationId);

            if (! auth()->user()?->managesReservation($reservation)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya user yang ditetapkan pada Pengelola Barang aset ini yang dapat menerima pengembalian.',
                ]);
            }

            if ($reservation->status !== ReservationStatus::CheckedOut->value) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya kebutuhan yang sedang dipakai yang dapat dicatat kembali oleh pengelola.',
                ]);
            }

            $this->storeReservationChecklists($type, $reservation, $checklistData);
            $receipt = $this->pendingReturnReceipt($type, $reservation);
            $receipt->forceFill([
                'initiated_by' => auth()->id(),
                'manager_confirmed_by' => auth()->id(),
                'manager_confirmed_at' => now(),
                'proof_path' => $checklistData['proof_path'] ?? $checklistData['photo'] ?? null,
                'notes' => $checklistData['receipt_notes'] ?? $checklistData['notes'] ?? null,
            ])->save();

            $previous = $reservation->status;
            $reservation->forceFill([
                'status' => ReservationStatus::ReturnRequested->value,
                'status_changed_by' => auth()->id(),
                'status_changed_at' => now(),
            ])->saveQuietly();
            $this->createStatusHistory($reservation, $previous, ReservationStatus::ReturnRequested->value, auth()->id(), 'Pengembalian fisik dicatat oleh pengelola; menunggu konfirmasi peminjam.');
            $this->syncStatus($reservation->activity->fresh(), notify: false);

            return $reservation->activity->fresh();
        }, 3);

        $this->notifications->returnConfirmationRequired($activity, forBorrower: true);
    }

    public function requestReservationReturn(string $type, string $reservationId, array $checklistData): void
    {
        $modelClass = $this->reservationModelClass($type);
        $activity = DB::transaction(function () use ($checklistData, $modelClass, $reservationId, $type): G004M008Activity {
            $reservation = $modelClass::query()->with('activity')->lockForUpdate()->findOrFail($reservationId);
            $user = auth()->user();

            if (! $user?->belongsToUnit($reservation->activity?->g001_m001_unit_id)) {
                throw ValidationException::withMessages(['status' => 'Hanya unit pemohon yang dapat mengajukan pengembalian.']);
            }

            if ($reservation->status !== ReservationStatus::CheckedOut->value) {
                throw ValidationException::withMessages(['status' => 'Pengembalian hanya dapat diajukan saat kebutuhan sedang dipakai.']);
            }

            $this->storeReservationChecklists($type, $reservation, $checklistData);
            $receipt = $this->pendingReturnReceipt($type, $reservation);
            $receipt->forceFill([
                'initiated_by' => $user->id,
                'borrower_confirmed_by' => $user->id,
                'borrower_confirmed_at' => now(),
                'proof_path' => $checklistData['proof_path'] ?? $checklistData['photo'] ?? null,
                'notes' => $checklistData['receipt_notes'] ?? $checklistData['notes'] ?? null,
            ])->save();

            $reservation->update(['status' => ReservationStatus::ReturnRequested->value]);
            $this->syncStatus($reservation->activity->fresh(), notify: false);

            return $reservation->activity->fresh();
        }, 3);

        $this->notifications->returnConfirmationRequired($activity, forBorrower: false);
    }

    public function confirmReturn(string $type, string $reservationId): void
    {
        $modelClass = $this->reservationModelClass($type);

        DB::transaction(function () use ($modelClass, $reservationId, $type): void {
            $reservation = $modelClass::query()->with(['activity', 'returnReceipt'])->lockForUpdate()->findOrFail($reservationId);
            $receipt = $reservation->returnReceipt;
            $user = auth()->user();

            if ($reservation->status !== ReservationStatus::ReturnRequested->value || ! $receipt) {
                throw ValidationException::withMessages(['status' => 'Tidak ada serah-terima pengembalian yang menunggu konfirmasi.']);
            }

            if (! $receipt->manager_confirmed_at
                && $user?->managesReservation($reservation)
                && $receipt->borrower_confirmed_by !== $user->id) {
                $receipt->forceFill(['manager_confirmed_by' => $user->id, 'manager_confirmed_at' => now()]);
            } elseif (! $receipt->borrower_confirmed_at
                && $user?->belongsToUnit($reservation->activity?->g001_m001_unit_id)
                && $receipt->manager_confirmed_by !== $user->id) {
                $receipt->forceFill(['borrower_confirmed_by' => $user->id, 'borrower_confirmed_at' => now()]);
            } else {
                throw ValidationException::withMessages(['status' => 'Konfirmasi harus dilakukan oleh pihak lain yang belum mengonfirmasi.']);
            }

            if ($receipt->borrower_confirmed_at && $receipt->manager_confirmed_at) {
                $receipt->completed_at = now();
            }
            $receipt->save();

            if ($receipt->completed_at) {
                $this->processReservation($type, $reservationId, ReservationStatus::Returned);
            }
        }, 3);
    }

    public function canConfirmReturn(Model $reservation, ?User $user = null): bool
    {
        $user ??= auth()->user();
        $receipt = $reservation->returnReceipt;

        return $reservation->status === ReservationStatus::ReturnRequested->value
            && $receipt
            && ((! $receipt->manager_confirmed_at
                && $user?->managesReservation($reservation)
                && $receipt->borrower_confirmed_by !== $user->id)
                || (! $receipt->borrower_confirmed_at
                    && $user?->belongsToUnit($reservation->activity?->g001_m001_unit_id)
                    && $receipt->manager_confirmed_by !== $user->id));
    }

    public function canDecideReservation(Model $reservation, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $reservation->status === ReservationStatus::Submitted->value
            && (! $reservation->activity?->hold_expires_at || $reservation->activity->hold_expires_at->isFuture())
            && ($user?->managesReservation($reservation) ?? false);
    }

    public function canCheckoutReservation(Model $reservation, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $reservation->status === ReservationStatus::Approved->value
            && ($user?->managesReservation($reservation) ?? false);
    }

    public function canRecordManagedReturn(Model $reservation, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $reservation->status === ReservationStatus::CheckedOut->value
            && ($user?->managesReservation($reservation) ?? false);
    }

    public function canRequestReservationReturn(Model $reservation, ?User $user = null): bool
    {
        $user ??= auth()->user();

        return $reservation->status === ReservationStatus::CheckedOut->value
            && ($user?->belongsToUnit($reservation->activity?->g001_m001_unit_id) ?? false);
    }

    /** @return list<string> */
    public function availableQuickActions(Model $reservation, ?User $user = null): array
    {
        $user ??= auth()->user();

        if (! $user) {
            return [];
        }

        if ($this->canConfirmReturn($reservation, $user)) {
            return ['confirmReturn'];
        }

        if ($this->canDecideReservation($reservation, $user)) {
            return ['approve', 'reject'];
        }

        if ($this->canCheckoutReservation($reservation, $user)) {
            return ['checkout'];
        }

        return array_values(array_filter([
            $this->canRecordManagedReturn($reservation, $user) ? 'recordReturn' : null,
            $this->canRequestReservationReturn($reservation, $user) ? 'requestReturn' : null,
        ]));
    }

    public function processReservation(
        string $type,
        string $reservationId,
        ReservationStatus $status,
        ?string $rejectionReason = null,
        array $assignment = [],
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

        $name = DB::transaction(function () use ($activityId, $assignment, $modelClass, $rejectionReason, $reservationId, $status, $type): string {
            if ($activityId) {
                G004M008Activity::query()->lockForUpdate()->findOrFail($activityId);
            }

            $reservation = $modelClass::query()->lockForUpdate()->findOrFail($reservationId);

            if ($activityId && $reservation->g004_m008_activity_id !== $activityId) {
                throw ValidationException::withMessages([
                    'status' => 'Data pengajuan berubah saat diproses. Muat ulang halaman dan coba kembali.',
                ]);
            }

            $borrowerCompletingCheckout = $status === ReservationStatus::CheckedOut
                && auth()->user()?->belongsToUnit($reservation->activity?->g001_m001_unit_id)
                && $reservation->outboundReceipt?->completed_at
                && $reservation->outboundReceipt?->borrower_confirmed_by === auth()->id()
                && $reservation->outboundReceipt?->manager_confirmed_by !== auth()->id();

            $borrowerCompletingReturn = $status === ReservationStatus::Returned
                && auth()->user()?->belongsToUnit($reservation->activity?->g001_m001_unit_id)
                && $reservation->returnReceipt?->completed_at;

            if (! auth()->user()?->managesReservation($reservation) && ! $borrowerCompletingReturn && ! $borrowerCompletingCheckout) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya user yang ditetapkan pada Pengelola Barang aset ini yang dapat memproses peminjaman.',
                ]);
            }

            if ($status === ReservationStatus::CheckedOut
                && $reservation->outboundReceipt
                && ! $reservation->outboundReceipt->completed_at) {
                throw ValidationException::withMessages([
                    'status' => 'Serah-terima awal belum dikonfirmasi oleh peminjam.',
                ]);
            }

            if ($reservation instanceof G005M019VehicleReservation
                && in_array($status, [ReservationStatus::Approved, ReservationStatus::CheckedOut], true)) {
                $vehicle = $reservation->vehicle;
                $driverId = array_key_exists('driver_id', $assignment)
                    ? (filled($assignment['driver_id']) ? (int) $assignment['driver_id'] : null)
                    : ($reservation->g008_m018_driver_id ?: $vehicle?->default_driver_id);
                $assistantId = array_key_exists('assistant_id', $assignment)
                    ? (filled($assignment['assistant_id']) ? (int) $assignment['assistant_id'] : null)
                    : $reservation->vehicle_assistant_id;

                app(VehicleAssignmentService::class)->assign(
                    $reservation,
                    $driverId ? (int) $driverId : null,
                    $assistantId ? (int) $assistantId : null,
                    $status === ReservationStatus::Approved
                        ? 'Penugasan pada persetujuan reservasi'
                        : 'Validasi personel sebelum pemberangkatan',
                    auth()->user(),
                );
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

            if ($status === ReservationStatus::CheckedOut && ! $reservation->outboundReceipt()->exists()) {
                // Programmatic/legacy clients may still call processReservation directly.
                // Explicitly mark this as an unverified historical compatibility path,
                // never impersonate a borrower or fabricate a condition checklist.
                LoanHandoverReceipt::query()->create([
                    'receipt_number' => 'OUT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'reservation_type' => $type,
                    'reservation_id' => $reservationId,
                    'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
                    'direction' => 'checkout',
                    'initiated_by' => auth()->id(),
                    'manager_confirmed_by' => auth()->id(),
                    'manager_confirmed_at' => now(),
                    'fallback_reason' => 'Jalur kompatibilitas legacy tanpa konfirmasi peminjam; perlu verifikasi manual.',
                    'completed_at' => now(),
                ]);
            }

            if ($status === ReservationStatus::Returned) {
                if ($reservation instanceof G005M009ItemReservation) {
                    $conditions = $reservation->returnChecklists()
                        ->pluck('is_ok', 'g002_m015_item_instance_id')
                        ->map(fn ($condition): bool => (bool) $condition)
                        ->all();
                    $this->itemAllocations->releaseByInstance($reservation, $conditions);
                } elseif (! (bool) $reservation->returnChecklists()->value('is_ok')) {
                    $asset = match (true) {
                        $reservation instanceof G005M010RoomReservation => $reservation->room,
                        $reservation instanceof G005M019VehicleReservation => $reservation->vehicle,
                    };

                    $asset?->update([
                        'is_borrowable' => false,
                        'status' => 'perlu_perbaikan',
                    ]);
                }

                // Ticketing is a separate workflow: a damaged asset remains blocked
                // until physical checks authorize its return to inventory.
                app(TicketService::class)->damagedReturn($type, $reservation);
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

    public function notifyOverdueLoans(): int
    {
        $notified = 0;

        foreach (['item', 'room', 'vehicle'] as $type) {
            $modelClass = $this->reservationModelClass($type);
            $modelClass::query()
                ->whereIn('status', [ReservationStatus::CheckedOut->value, ReservationStatus::ReturnRequested->value])
                ->where('end_time', '<', now())
                ->where(function ($query): void {
                    $query->whereNull('overdue_notified_at')
                        ->orWhere('overdue_notified_at', '<=', now()->subDay());
                })
                ->with(['activity.user'])
                ->chunkById(100, function ($reservations) use (&$notified, $type): void {
                    foreach ($reservations as $reservation) {
                        $this->notifications->overdue($type, $reservation);
                        $reservation->forceFill(['overdue_notified_at' => now()])->saveQuietly();
                        $notified++;
                    }
                });
        }

        return $notified;
    }

    public function correctReservationStatus(string $type, string $reservationId, string $targetStatus, string $reason): void
    {
        $user = auth()->user();
        if (! $user?->isAdmin()) {
            throw ValidationException::withMessages(['status' => 'Hanya admin yang dapat membuat koreksi administratif.']);
        }

        if ($targetStatus !== ReservationStatus::CheckedOut->value) {
            throw ValidationException::withMessages(['status' => 'Koreksi saat ini hanya dapat membuka kembali peminjaman ke status Sedang Dipakai.']);
        }

        $modelClass = $this->reservationModelClass($type);
        DB::transaction(function () use ($modelClass, $reason, $reservationId, $targetStatus, $type, $user): void {
            $reservation = $modelClass::query()->with('activity')->lockForUpdate()->findOrFail($reservationId);
            $from = $reservation->status;

            if (! in_array($from, [ReservationStatus::ReturnRequested->value, ReservationStatus::Returned->value], true)) {
                throw ValidationException::withMessages(['status' => 'Hanya proses pengembalian yang dapat dibuka kembali.']);
            }

            LoanReservationCorrection::query()->create([
                'reservation_type' => $type,
                'reservation_id' => $reservation->getKey(),
                'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
                'field' => 'status',
                'old_value' => $from,
                'new_value' => $targetStatus,
                'reason' => $reason,
                'corrected_by' => $user->id,
            ]);

            $reservation->forceFill([
                'status' => $targetStatus,
                'returned_at' => null,
                'status_changed_by' => $user->id,
                'status_changed_at' => now(),
            ])->saveQuietly();
            $this->createStatusHistory($reservation, $from, $targetStatus, $user->id, '[KOREKSI ADMIN] '.$reason);

            if ($reservation instanceof G005M009ItemReservation) {
                $instanceIds = $reservation->item_reservation_detail()->pluck('g002_m015_item_instance_id');
                \App\Models\G002M015ItemInstance::query()->whereKey($instanceIds)->update([
                    'status' => ReservationStatus::CheckedOut->value,
                    'is_available' => false,
                ]);
            }

            $this->syncStatus($reservation->activity->fresh(), notify: false);
        }, 3);
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
            $managerInitiated = $reservation->returnReceipt?->manager_confirmed_at
                && ! $reservation->returnReceipt?->borrower_confirmed_at;

            if (! $user?->belongsToUnit($reservation->activity?->g001_m001_unit_id)
                && ! ($managerInitiated && $user?->managesReservation($reservation))) {
                throw ValidationException::withMessages(['status' => 'Hanya unit pemohon yang dapat mengajukan pengembalian.']);
            }

            $this->assertTransition($from, ReservationStatus::CheckedOut, 'Pengembalian hanya dapat diajukan saat kebutuhan sedang dipakai.');

            return;
        }

        $borrowerCompletingCheckout = $to === ReservationStatus::CheckedOut
            && $reservation->outboundReceipt?->completed_at
            && $reservation->outboundReceipt?->borrower_confirmed_by === $user?->id
            && $reservation->outboundReceipt?->manager_confirmed_by !== $user?->id
            && $user?->belongsToUnit($reservation->activity?->g001_m001_unit_id);

        $borrowerCompletingReturn = $to === ReservationStatus::Returned
            && $reservation->returnReceipt?->completed_at
            && $user?->belongsToUnit($reservation->activity?->g001_m001_unit_id);

        if (! $user?->managesReservation($reservation) && ! $borrowerCompletingReturn && ! $borrowerCompletingCheckout) {
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
            ReservationStatus::Returned => $this->assertReturnTransition($reservation, $from),
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

    private function assertReturnTransition(Model $reservation, ReservationStatus $from): void
    {
        if (! in_array($from, [ReservationStatus::CheckedOut, ReservationStatus::ReturnRequested], true)) {
            throw ValidationException::withMessages([
                'status' => 'Kebutuhan hanya dapat dikembalikan saat sedang dipakai atau menunggu konfirmasi pengembalian.',
            ]);
        }

        if (! $reservation->returnChecklists()->exists()) {
            throw ValidationException::withMessages([
                'checklist' => 'Checklist kondisi aset wajib diisi sebelum pengembalian dikonfirmasi.',
            ]);
        }

        $receipt = $reservation->returnReceipt;
        if (! $receipt?->borrower_confirmed_at || ! $receipt?->manager_confirmed_at || ! $receipt?->completed_at) {
            throw ValidationException::withMessages([
                'receipt' => 'Serah-terima wajib dikonfirmasi oleh peminjam dan pengelola.',
            ]);
        }
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
                $reservation->forceFill([
                    'status' => $to->value,
                    'status_changed_by' => $user?->id,
                    'status_changed_at' => now(),
                ])->saveQuietly();
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

    private function createItemReservation(
        G004M008Activity $activity,
        array $need,
        int $index,
        Carbon $start,
        Carbon $end,
        ReservationStatus $status,
        bool $validateAvailability = true,
    ): void {
        $itemId = (int) ($need['item_id'] ?? 0);
        $quantity = (int) ($need['quantity'] ?? 0);
        $item = G002M007Item::query()->lockForUpdate()->find($itemId);

        if (! $item || ! $item->is_borrowable || $quantity < 1) {
            throw ValidationException::withMessages([
                "data.needs.{$index}.item_id" => 'Barang tidak valid atau tidak dapat dipinjam.',
            ]);
        }

        $available = $this->availability->availableItemQuantity($itemId, $start, $end);

        if ($validateAvailability && $quantity > $available) {
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

    private function createRoomReservation(
        G004M008Activity $activity,
        array $need,
        int $index,
        Carbon $start,
        Carbon $end,
        ReservationStatus $status,
        bool $validateAvailability = true,
    ): void {
        $roomId = (int) ($need['room_id'] ?? 0);
        $room = G003M006Room::query()->lockForUpdate()->find($roomId);

        if (! $room || ! $room->is_borrowable || ($validateAvailability && ! $this->availability->roomIsAvailable($roomId, $start, $end))) {
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

    private function createVehicleReservation(
        G004M008Activity $activity,
        array $need,
        int $index,
        Carbon $start,
        Carbon $end,
        ReservationStatus $status,
        bool $validateAvailability = true,
    ): void {
        $vehicleId = (int) ($need['vehicle_id'] ?? 0);
        $vehicle = G008M017Vehicle::query()->lockForUpdate()->find($vehicleId);

        if (! $vehicle || ! $vehicle->is_borrowable || ($validateAvailability && ! $this->availability->vehicleIsAvailable($vehicleId, $start, $end))) {
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
            if ($this->availability->availableItemQuantity($reservation->g002_m007_item_id, $activity->start_time, $activity->end_time, locking: true) < $reservation->quantity) {
                throw ValidationException::withMessages(['status' => "Stok {$reservation->item?->name} tidak lagi mencukupi untuk jadwal ini."]);
            }
        }

        foreach ($activity->room_reservation as $reservation) {
            if (! $this->availability->roomIsAvailable($reservation->g003_m006_room_id, $activity->start_time, $activity->end_time, locking: true)) {
                throw ValidationException::withMessages(['status' => "{$reservation->room?->name} tidak lagi tersedia untuk jadwal ini."]);
            }
        }

        foreach ($activity->vehicle_reservation as $reservation) {
            if (! $this->availability->vehicleIsAvailable($reservation->g008_m017_vehicle_id, $activity->start_time, $activity->end_time, locking: true)) {
                throw ValidationException::withMessages(['status' => "{$reservation->vehicle?->name} tidak lagi tersedia untuk jadwal ini."]);
            }
        }
    }

    /** @return class-string<Model> */
    private function reservationModelClass(string $type): string
    {
        return match ($type) {
            'item' => G005M009ItemReservation::class,
            'room' => G005M010RoomReservation::class,
            'vehicle' => G005M019VehicleReservation::class,
            default => throw ValidationException::withMessages(['status' => 'Jenis kebutuhan tidak valid.']),
        };
    }

    private function pendingReturnReceipt(string $type, Model $reservation): LoanHandoverReceipt
    {
        return LoanHandoverReceipt::query()->firstOrCreate([
            'reservation_type' => $type,
            'reservation_id' => $reservation->getKey(),
            'direction' => 'return',
            'completed_at' => null,
        ], [
            'receipt_number' => 'RTN-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
            'initiated_by' => auth()->id(),
        ]);
    }

    private function storeReservationChecklists(string $type, Model $reservation, array $data): void
    {
        if ($type !== 'item') {
            $this->validateChecklistCondition($data);
            LoanReservationChecklist::query()->updateOrCreate([
                'reservation_type' => $type,
                'reservation_id' => $reservation->getKey(),
                'g002_m015_item_instance_id' => null,
            ], [
                'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
                'checked_by' => auth()->id(),
                'is_ok' => (bool) ($data['is_ok'] ?? false),
                'notes' => $data['notes'] ?? null,
                'photo' => $data['photo'] ?? null,
                'checked_at' => now(),
            ]);

            return;
        }

        /** @var G005M009ItemReservation $reservation */
        $details = $reservation->item_reservation_detail()->with('item_instance')->get();
        if ($details->isEmpty()) {
            throw ValidationException::withMessages([
                'checklist' => 'Barang satuan belum dialokasikan sehingga checklist pengembalian belum dapat diisi.',
            ]);
        }

        $submitted = collect($data['instances'] ?? []);
        if ($submitted->isEmpty()) {
            $this->validateChecklistCondition($data);
            $submitted = $details->map(fn ($detail): array => [
                'item_instance_id' => $detail->g002_m015_item_instance_id,
                'is_ok' => (bool) ($data['is_ok'] ?? false),
                'notes' => $data['notes'] ?? null,
                'photo' => $data['photo'] ?? null,
            ]);
        }

        $allocatedIds = $details->pluck('g002_m015_item_instance_id')->map(fn ($id): int => (int) $id)->sort()->values();
        $submittedIds = $submitted->pluck('item_instance_id')->map(fn ($id): int => (int) $id)->sort()->values();
        if ($allocatedIds->all() !== $submittedIds->all()) {
            throw ValidationException::withMessages([
                'checklist' => 'Checklist wajib diisi tepat satu kali untuk setiap barang satuan yang dialokasikan.',
            ]);
        }

        foreach ($submitted as $index => $instanceData) {
            $this->validateChecklistCondition($instanceData, "instances.{$index}");
            LoanReservationChecklist::query()->updateOrCreate([
                'reservation_type' => $type,
                'reservation_id' => $reservation->getKey(),
                'g002_m015_item_instance_id' => (int) $instanceData['item_instance_id'],
            ], [
                'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
                'checked_by' => auth()->id(),
                'is_ok' => (bool) ($instanceData['is_ok'] ?? false),
                'notes' => $instanceData['notes'] ?? null,
                'photo' => $instanceData['photo'] ?? null,
                'checked_at' => now(),
            ]);
        }
    }

    private function validateChecklistCondition(array $data, string $prefix = 'checklist'): void
    {
        if (! (bool) ($data['is_ok'] ?? false) && blank($data['notes'] ?? null)) {
            throw ValidationException::withMessages([
                "{$prefix}.notes" => 'Catatan kerusakan atau masalah wajib diisi jika kondisi aset tidak baik.',
            ]);
        }
    }
}
