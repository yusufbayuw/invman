<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\G002M015ItemInstance;
use App\Models\G004M008Activity;
use App\Models\G005M009ItemReservation;
use App\Models\LoanCheckoutChecklist;
use App\Models\LoanHandoverReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanCheckoutService
{
    public function __construct(private readonly LoanHandoverQrService $qr) {}

    /** @return array<int, array<string, mixed>> */
    public function instanceOptions(Model $reservation): array
    {
        if (! $reservation instanceof G005M009ItemReservation || $reservation->item?->isBulk()) {
            return [];
        }

        $allocated = $reservation->item_reservation_detail()
            ->with('item_instance')
            ->get()->pluck('item_instance')->filter();

        $instances = $allocated->isNotEmpty()
            ? $allocated
            : G002M015ItemInstance::query()
                ->where('g002_m007_item_id', $reservation->g002_m007_item_id)
                ->where('is_borrowable', true)
                ->where('is_available', true)
                ->orderBy('id')
                ->limit((int) $reservation->quantity)
                ->get();

        return $instances->map(fn (G002M015ItemInstance $instance): array => [
            'item_instance_id' => $instance->id,
            'instance_label' => $instance->code ?: ($instance->name ?? '#'.$instance->id),
            'is_ok' => true,
        ])->values()->all();
    }

    public function canConfirm(Model $reservation, ?User $user = null): bool
    {
        $user ??= auth()->user();
        $receipt = $reservation->outboundReceipt;

        return $reservation->status === ReservationStatus::Approved->value
            && $receipt && $receipt->manager_confirmed_at && ! $receipt->borrower_confirmed_at
            && $receipt->manager_confirmed_by !== $user?->id
            && ($user?->belongsToUnit($reservation->activity?->g001_m001_unit_id) ?? false);
    }

    /** Store manager's initial condition record; status stays approved until borrower accepts. */
    public function begin(string $type, string $id, array $data): LoanHandoverReceipt
    {
        return DB::transaction(function () use ($type, $id, $data): LoanHandoverReceipt {
            $reservation = $this->lockReservation($type, $id);
            $user = auth()->user();

            if (! $user?->managesReservation($reservation) || $reservation->status !== ReservationStatus::Approved->value) {
                throw ValidationException::withMessages(['status' => 'Hanya pengelola dapat memulai penyerahan yang telah disetujui.']);
            }

            if ($reservation->outboundReceipt()->exists()) {
                throw ValidationException::withMessages(['status' => 'Penyerahan sudah dicatat. Tunggu konfirmasi atau gunakan penanganan khusus.']);
            }

            $rows = $this->validatedChecklist($type, $reservation, $data);
            $odometer = $data['checkout_odometer'] ?? null;
            if ($type === 'vehicle' && filled($odometer) && (! ctype_digit((string) $odometer) || (int) $odometer < 0)) {
                throw ValidationException::withMessages(['data.checkout_odometer' => 'Kilometer awal harus bilangan bulat tidak negatif.']);
            }

            $receipt = LoanHandoverReceipt::query()->create([
                'receipt_number' => 'OUT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'reservation_type' => $type,
                'reservation_id' => $id,
                'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
                'direction' => 'checkout',
                'initiated_by' => $user->id,
                'manager_confirmed_by' => $user->id,
                'manager_confirmed_at' => now(),
                'proof_path' => $data['proof_path'] ?? null,
                'notes' => $data['receipt_notes'] ?? null,
            ]);
            if ($type === 'vehicle' && filled($odometer)) {
                $receipt->forceFill(['checkout_odometer' => (int) $odometer])->save();
            }

            foreach ($rows as $row) {
                $checklist = new LoanCheckoutChecklist;
                $checklist->forceFill([
                    'reservation_type' => $type,
                    'reservation_id' => $id,
                    'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
                    'g002_m015_item_instance_id' => $row['item_instance_id'] ?? null,
                    'checked_by' => $user->id,
                    'is_ok' => (bool) $row['is_ok'],
                    'notes' => $row['notes'] ?? null,
                    'photo' => $row['photo'] ?? null,
                    'checked_at' => now(),
                ])->save();
            }

            return $receipt;
        }, 3);
    }

    public function confirm(string $type, string $id): void
    {
        DB::transaction(function () use ($type, $id): void {
            $reservation = $this->lockReservation($type, $id);
            if (! $this->canConfirm($reservation)) {
                throw ValidationException::withMessages(['status' => 'Penerimaan hanya dapat dikonfirmasi oleh pihak peminjam yang berbeda.']);
            }

            $receipt = $reservation->outboundReceipt;
            // Persist the second actor before the existing service changes status.
            // Both writes roll back if the allocation/driver validation fails.
            $receipt->forceFill([
                'borrower_confirmed_by' => auth()->id(),
                'borrower_confirmed_at' => now(),
                'completed_at' => now(),
            ])->save();
            $reservation->unsetRelation('outboundReceipt');

            app(LoanRequestService::class)->processReservation(
                $type, $id, ReservationStatus::CheckedOut,
            );

            $this->assertAllocatedInstancesMatchChecklist($type, $reservation);
        }, 3);
    }

    /** An explicitly audited exception when borrower cannot confirm in person. */
    public function fallback(string $type, string $id, array $data): void
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Alasan pengecualian minimal 10 dan maksimal 2000 karakter.']);
        }

        DB::transaction(function () use ($type, $id, $reason): void {
            $reservation = $this->lockReservation($type, $id);
            if (! auth()->user()?->managesReservation($reservation)
                || $reservation->status !== ReservationStatus::Approved->value) {
                throw ValidationException::withMessages(['status' => 'Hanya pengelola dapat mencatat penyerahan khusus.']);
            }

            $receipt = $reservation->outboundReceipt;
            if ($receipt?->completed_at) {
                throw ValidationException::withMessages(['status' => 'Penyerahan ini sudah diselesaikan.']);
            }

            // The exception waives only the absent borrower's confirmation.
            // Physical inspection must already have been recorded by begin().
            $checks = $reservation->outboundChecklists()->get();
            $expectedCount = $type === 'item' && ! $reservation->item?->isBulk() ? (int) $reservation->quantity : 1;
            if (! $receipt?->manager_confirmed_at || $expectedCount < 1
                || $checks->count() !== $expectedCount
                || $checks->contains(fn ($check) => ! $check->checked_at
                    || (! $check->is_ok && blank($check->notes)))) {
                throw ValidationException::withMessages([
                    'status' => 'Catat kondisi awal setiap aset sebelum menggunakan Penyerahan Khusus.',
                ]);
            }

            $receipt->forceFill([
                'fallback_reason' => $reason,
                'completed_at' => now(),
            ])->save();

            app(LoanRequestService::class)->processReservation(
                $type, $id, ReservationStatus::CheckedOut,
            );
            $this->assertAllocatedInstancesMatchChecklist($type, $reservation);
            app(HierarchyOverrideService::class)->record(
                auth()->user(), $reservation->activity?->user, 'loan',
                $reservation->g004_m008_activity_id, 'checkout_confirmation_exception',
                $reason, ReservationStatus::Approved->value, ReservationStatus::CheckedOut->value,
                ['reservation_type' => $type, 'reservation_id' => $id,
                    'receipt_id' => $receipt->id, 'borrower_confirmed' => false],
            );
        }, 3);
    }

    private function assertAllocatedInstancesMatchChecklist(string $type, Model $reservation): void
    {
        if ($type !== 'item') {
            return;
        }
        if ($reservation->item?->isBulk()) {
            if ($reservation->item_reservation_detail()->exists()
                || $reservation->outboundChecklists()->count() !== 1
                || $reservation->outboundChecklists()->whereNotNull('g002_m015_item_instance_id')->exists()) {
                throw ValidationException::withMessages(['status' => 'Bukti serah-terima bulk tidak valid.']);
            }
            return;
        }
        $expected = $reservation->outboundChecklists()
            ->pluck('g002_m015_item_instance_id')->map(fn ($id): int => (int) $id)
            ->sort()->values()->all();
        $actual = $reservation->item_reservation_detail()
            ->pluck('g002_m015_item_instance_id')->map(fn ($id): int => (int) $id)
            ->sort()->values()->all();
        if ($expected !== $actual || count($expected) !== (int) $reservation->quantity) {
            throw ValidationException::withMessages([
                'status' => 'Barang satuan berubah sejak pemeriksaan awal; penyerahan dibatalkan. Hubungi pengelola.',
            ]);
        }
    }

    private function lockReservation(string $type, string $id): Model
    {
        $reservation = $this->qr->resolve($type, $id);
        if (! $reservation) {
            throw ValidationException::withMessages(['status' => 'Reservasi tidak ditemukan.']);
        }
        G004M008Activity::query()->whereKey($reservation->g004_m008_activity_id)->lockForUpdate()->firstOrFail();
        $fresh = $reservation->newQuery()
            ->with(['activity', 'outboundReceipt'])->lockForUpdate()->findOrFail($id);

        return $fresh;
    }

    /** @return array<int, array<string, mixed>> */
    private function validatedChecklist(string $type, Model $reservation, array $data): array
    {
        if ($type !== 'item') {
            $rows = [[
                'is_ok' => (bool) ($data['is_ok'] ?? false),
                'notes' => $data['notes'] ?? null,
                'photo' => $data['photo'] ?? null,
            ]];
        } elseif ($reservation->item?->isBulk()) {
            $rows = [[
                'is_ok' => (bool) ($data['is_ok'] ?? false),
                'notes' => $data['notes'] ?? null,
                'photo' => $data['photo'] ?? null,
            ]];
        } else {
            $options = $this->instanceOptions($reservation);
            if (count($options) !== (int) $reservation->quantity) {
                throw ValidationException::withMessages(['data.instances' => 'Jumlah barang satuan yang tersedia tidak mencukupi.']);
            }

            $submitted = array_values($data['instances'] ?? []);
            $expected = collect($options)->pluck('item_instance_id')->map(fn ($v): int => (int) $v)->sort()->values()->all();
            $actual = collect($submitted)->pluck('item_instance_id')->map(fn ($v): int => (int) $v)->sort()->values()->all();
            if ($expected !== $actual) {
                throw ValidationException::withMessages(['data.instances' => 'Checklist harus memuat setiap barang satuan yang tepat satu kali.']);
            }
            $rows = $submitted;
        }

        foreach ($rows as $index => $row) {
            if (! ($row['is_ok'] ?? false) && blank($row['notes'] ?? null)) {
                throw ValidationException::withMessages([
                    "data.instances.{$index}.notes" => 'Catatan kondisi wajib jika aset tidak dalam kondisi baik.',
                ]);
            }
        }

        return $rows;
    }
}
