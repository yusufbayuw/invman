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
        if (! $reservation instanceof G005M009ItemReservation) {
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

            if ($type === 'item') {
                // Confirm exactly the instances recorded at handover, not another
                // set silently chosen by a changed inventory allocation.
                $expected = LoanCheckoutChecklist::query()
                    ->where('reservation_type', 'item')->where('reservation_id', $id)
                    ->whereNotNull('g002_m015_item_instance_id')
                    ->pluck('g002_m015_item_instance_id')->map(fn ($x): int => (int) $x)->sort()->values()->all();
                $actual = G005M009ItemReservation::query()->findOrFail($id)
                    ->item_reservation_detail()->pluck('g002_m015_item_instance_id')
                    ->map(fn ($x): int => (int) $x)->sort()->values()->all();
                if ($expected !== $actual) {
                    throw ValidationException::withMessages([
                        'status' => 'Barang satuan berubah sejak pemeriksaan awal; penyerahan dibatalkan. Hubungi pengelola.',
                    ]);
                }
            }
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

            if (! $receipt) {
                $receipt = LoanHandoverReceipt::query()->create([
                    'receipt_number' => 'OUT-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'reservation_type' => $type,
                    'reservation_id' => $id,
                    'g004_m008_activity_id' => $reservation->g004_m008_activity_id,
                    'direction' => 'checkout',
                    'initiated_by' => auth()->id(),
                ]);
            }

            $receipt->forceFill([
                'manager_confirmed_by' => auth()->id(),
                'manager_confirmed_at' => now(),
                'fallback_reason' => $reason,
                'completed_at' => now(),
            ])->save();

            app(LoanRequestService::class)->processReservation(
                $type, $id, ReservationStatus::CheckedOut,
            );
        }, 3);
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
