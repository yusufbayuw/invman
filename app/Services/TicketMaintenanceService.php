<?php

namespace App\Services;

use App\Models\G002M015ItemInstance;
use App\Models\G003M006Room;
use App\Models\G008M017Vehicle;
use App\Models\Ticket;
use App\Models\TicketMaintenanceCase;
use App\Models\TicketTeamMember;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketMaintenanceService
{
    public function __construct(
        private readonly TicketVisibility $visibility,
        private readonly TicketSlaService $sla,
    ) {}

    /** V3 is opt-in per new reporting category; legacy tickets remain on V1. */
    public function initialize(Ticket $ticket): TicketMaintenanceCase
    {
        $deadlines = $this->sla->deadlines($ticket);
        return TicketMaintenanceCase::query()->firstOrCreate(['ticket_id' => $ticket->id], [
            ...$deadlines,
            'asset_hold_at' => $ticket->assets()->exists() ? now() : null,
        ]);
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['maintenance' => $message]);
    }

    private function locked(Ticket $ticket): Ticket
    {
        return Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
    }

    private function case(Ticket $ticket): TicketMaintenanceCase
    {
        return TicketMaintenanceCase::query()->lockForUpdate()->findOrFail($ticket->id);
    }

    private function record(Ticket $ticket, ?User $actor, string $action, ?string $from = null, ?string $to = null, ?string $notes = null): void
    {
        $ticket->events()->create([
            'actor_id' => $actor?->id, 'action' => $action,
            'from_value' => $from, 'to_value' => $to, 'notes' => $notes,
        ]);
    }

    /** Manager approves and assigns. No technician may self-approve. */
    public function approve(Ticket $ticket, User $manager, int $technicianId, string $priority = 'normal', ?string $notes = null): void
    {
        if (! $manager->isFacility()) {
            $this->reject('Hanya fasilitas/admin dapat menyetujui perbaikan.');
        }
        if (! in_array($priority, ['low', 'normal', 'high', 'critical'], true)) {
            $this->reject('Prioritas tidak valid.');
        }
        DB::transaction(function () use ($ticket, $manager, $technicianId, $priority, $notes): void {
            $locked = $this->locked($ticket);
            $case = $this->case($locked);
            if ($locked->status !== 'open' || $case->approved_at) {
                $this->reject('Tiket bukan pengajuan baru.');
            }
            $technician = User::query()->find($technicianId);
            if (! $technician || ! TicketTeamMember::qualifiesFor($locked, $technician)) {
                $this->reject('Petugas tidak aktif atau berada di luar tim/pengelola tiket.');
            }
            $old = $locked->priority;
            $locked->forceFill([
                'assigned_to' => $technicianId, 'priority' => $priority, 'status' => 'triaged',
            ])->save();
            $case->forceFill([
                'approved_by' => $manager->id, 'approved_at' => now(),
                'approval_notes' => $notes, 'responded_at' => now(),
                ...$this->sla->deadlines($locked, $priority),
            ])->save();
            $this->record($locked, $manager, 'maintenance_approved', $old, $priority, $notes);
            DB::afterCommit(function () use ($technician, $locked): void {
                Notification::make()->title('Tugas perbaikan: '.$locked->number)
                    ->body('Silakan terima dan kerjakan tiket.')->info()->sendToDatabase($technician);
            });
        }, 3);
    }

    /** A lead may delegate only an approved, unaccepted task within their own hierarchy. */
    public function canDelegate(Ticket $ticket, ?User $actor): bool
    {
        if (! $actor || ! $actor->is_enabled || ! $ticket->maintenance) {
            return false;
        }
        $case = $ticket->maintenance;
        if ($ticket->status !== 'triaged' || ! $case->approved_at || $case->accepted_at) {
            return false;
        }
        if ($actor->isFacility()) {
            return true;
        }
        $descendants = TicketTeamMember::descendantsOf($actor);
        return $descendants !== [] && (
            (int) $ticket->assigned_to === (int) $actor->id
            || in_array((int) $ticket->assigned_to, $descendants, true)
        );
    }

    /** @return array<int, string> */
    public function delegationOptions(Ticket $ticket, ?User $actor): array
    {
        if (! $this->canDelegate($ticket, $actor)) {
            return [];
        }
        $ids = $actor->isFacility()
            ? TicketTeamMember::query()->where('is_active', true)->pluck('user_id')->all()
            : TicketTeamMember::descendantsOf($actor);
        $ids = array_values(array_diff(array_map('intval', $ids), [(int) $ticket->reporter_id]));
        return User::query()->whereKey($ids)->where('is_enabled', true)->orderBy('name')
            ->get()->filter(fn (User $user): bool => TicketTeamMember::qualifiesFor($ticket, $user))
            ->mapWithKeys(function (User $user): array {
                $member = $user->ticketTeamMember;
                return [$user->id => $user->name.' · '.($member?->team_type === 'field' ? 'Lapangan' : 'Pendukung')
                    .' / '.($member?->position === 'lead' ? 'Koordinator' : 'Teknisi')];
            })->all();
    }

    public function delegate(Ticket $ticket, User $actor, int $technicianId, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 2000) {
            $this->reject('Alasan penugasan minimal lima karakter.');
        }
        DB::transaction(function () use ($ticket, $actor, $technicianId, $reason): void {
            $locked = $this->locked($ticket);
            $this->case($locked);
            if (! $this->canDelegate($locked, $actor)) {
                $this->reject('Hanya atasan yang berwenang dapat meneruskan tugas sebelum diterima petugas.');
            }
            $target = User::query()->find($technicianId);
            if (! $target || ! TicketTeamMember::qualifiesFor($locked, $target)
                || $technicianId === (int) $locked->assigned_to
                || (! $actor->isFacility() && ! in_array($technicianId, TicketTeamMember::descendantsOf($actor), true))) {
                $this->reject('Pilih anggota aktif di bawah hierarki Anda dan sesuai pengelola tiket.');
            }
            $from = (string) $locked->assigned_to;
            $locked->forceFill(['assigned_to' => $technicianId])->save();
            $this->record($locked, $actor, 'maintenance_delegated', $from, (string) $technicianId, trim($reason));
            DB::afterCommit(function () use ($locked, $target): void {
                Notification::make()->title('Penugasan lapangan: '.$locked->number)
                    ->body('Tugas telah didelegasikan kepada Anda. Buka tiket untuk mulai bekerja.')
                    ->info()->sendToDatabase($target);
            });
        }, 3);
    }

    public function accept(Ticket $ticket, User $technician): void
    {
        DB::transaction(function () use ($ticket, $technician): void {
            $locked = $this->locked($ticket);
            $case = $this->case($locked);
            if ($locked->assigned_to !== $technician->id
                || $locked->status !== 'triaged' || ! $case->approved_at) {
                $this->reject('Hanya teknisi yang ditugaskan dapat menerima pekerjaan yang disetujui.');
            }
            $locked->forceFill(['status' => 'in_progress'])->save();
            $case->forceFill(['accepted_by' => $technician->id, 'accepted_at' => now()])->save();
            $this->record($locked, $technician, 'maintenance_accepted', 'triaged', 'in_progress');
        }, 3);
    }

    /** Waiting for parts never secretly pauses SLA. */
    public function waiting(Ticket $ticket, User $technician, bool $waiting, string $reason): void
    {
        if (mb_strlen(trim($reason)) < 3 || mb_strlen($reason) > 2000) {
            $this->reject('Alasan perubahan kondisi minimal tiga karakter.');
        }
        DB::transaction(function () use ($ticket, $technician, $waiting, $reason): void {
            $locked = $this->locked($ticket);
            $this->case($locked);
            $from = $locked->status;
            if ($locked->assigned_to !== $technician->id
                || ! ($waiting ? $from === 'in_progress' : $from === 'waiting_parts')) {
                $this->reject('Status pekerjaan atau petugas tidak sesuai.');
            }
            $to = $waiting ? 'waiting_parts' : 'in_progress';
            $locked->forceFill(['status' => $to])->save();
            $this->record($locked, $technician, 'maintenance_waiting', $from, $to, $reason);
        }, 3);
    }

    /** @param list<array{name:string,quantity:numeric-string|int|float,unit?:string}> $materials */
    public function finish(Ticket $ticket, User $technician, string $diagnosis, string $actions, array $materials, string $outcome): void
    {
        if (mb_strlen(trim($diagnosis)) < 5 || mb_strlen(trim($actions)) < 5
            || mb_strlen($diagnosis) > 10000 || mb_strlen($actions) > 10000
            || ! in_array($outcome, ['complete', 'temporary', 'unrepairable'], true)
            || count($materials) > 30) {
            $this->reject('Diagnosis/tindakan minimal lima karakter, hasil atau jumlah material tidak valid.');
        }
        $clean = [];
        foreach ($materials as $line) {
            $name = trim((string) ($line['name'] ?? ''));
            $unit = trim((string) ($line['unit'] ?? 'pcs'));
            $qty = $line['quantity'] ?? null;
            if ($name === '' || mb_strlen($name) > 200 || $unit === '' || mb_strlen($unit) > 25
                || ! is_numeric($qty) || (float) $qty <= 0 || (float) $qty > 100000) {
                $this->reject('Material, satuan, dan jumlah harus valid.');
            }
            $clean[] = ['name' => $name, 'quantity' => (float) $qty, 'unit' => $unit];
        }

        DB::transaction(function () use ($ticket, $technician, $diagnosis, $actions, $clean, $outcome): void {
            $locked = $this->locked($ticket);
            $case = $this->case($locked);
            if ($locked->assigned_to !== $technician->id || $locked->status !== 'in_progress'
                || ! $case->accepted_at) {
                $this->reject('Hanya teknisi yang menerima tugas dapat mengirim hasil pekerjaan.');
            }
            $case->forceFill([
                'diagnosis' => trim($diagnosis), 'repair_actions' => trim($actions),
                'materials' => $clean, 'outcome' => $outcome,
                'work_finished_at' => now(), 'verified_by' => null,
                'verified_at' => null, 'final_approved_by' => null, 'final_approved_at' => null,
            ])->save();
            $locked->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();
            $this->record($locked, $technician, 'maintenance_work_finished', 'in_progress', $outcome);
            DB::afterCommit(function () use ($locked): void {
                if ($locked->reporter) {
                    Notification::make()->title('Verifikasi hasil perbaikan: '.$locked->number)
                        ->body('Periksa pekerjaan sebelum tiket ditutup.')->info()
                        ->sendToDatabase($locked->reporter);
                }
            });
        }, 3);
    }

    /** Reporter verifies the result; rejection sends the case back for correction. */
    public function verify(Ticket $ticket, User $reporter, bool $accepted, ?string $notes = null): void
    {
        DB::transaction(function () use ($ticket, $reporter, $accepted, $notes): void {
            $locked = $this->locked($ticket);
            $case = $this->case($locked);
            if ($locked->status !== 'resolved' || ! $case->work_finished_at
                || $case->verified_at || $locked->reporter_id !== $reporter->id
                || $locked->assigned_to === $reporter->id) {
                $this->reject('Hanya pelapor asli dapat memverifikasi pekerjaan yang telah diselesaikan.');
            }
            if (! $accepted && mb_strlen(trim((string) $notes)) < 5) {
                $this->reject('Alasan penolakan minimal lima karakter.');
            }
            if ($accepted) {
                $case->forceFill(['verified_by' => $reporter->id, 'verified_at' => now()])->save();
                $this->record($locked, $reporter, 'maintenance_verified', null, 'accepted', $notes);
            } else {
                $case->forceFill(['work_finished_at' => null, 'verified_by' => null, 'verified_at' => null])->save();
                $locked->forceFill(['status' => 'in_progress', 'resolved_at' => null])->save();
                $this->record($locked, $reporter, 'maintenance_verification_rejected', 'resolved', 'in_progress', $notes);
            }
        }, 3);
    }

    /** Final administrative sign-off is distinct from a physical readiness declaration. */
    public function close(Ticket $ticket, User $manager, bool $safe, ?string $notes = null): void
    {
        if (! $manager->isFacility()) {
            $this->reject('Pengesahan akhir hanya untuk manajer fasilitas/admin.');
        }
        DB::transaction(function () use ($ticket, $manager, $safe, $notes): void {
            $locked = $this->locked($ticket);
            $case = $this->case($locked);
            if ($locked->status !== 'resolved' || ! $case->verified_at
                || ! $case->approved_at || ! $case->work_finished_at
                || $case->final_approved_at) {
                $this->reject('Perlu persetujuan, hasil teknisi, dan verifikasi pelapor sebelum ditutup.');
            }
            if ($safe && $case->outcome !== 'complete') {
                $this->reject('Perbaikan sementara/tidak dapat diperbaiki tidak boleh ditandai layak pakai.');
            }
            $case->forceFill([
                'final_approved_by' => $manager->id, 'final_approved_at' => now(),
                'closure_notes' => $notes,
                'asset_released_at' => $safe ? now() : $case->asset_released_at,
                'asset_released_by' => $safe ? $manager->id : $case->asset_released_by,
            ])->save();
            $locked->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
            $this->record($locked, $manager, 'maintenance_closed', 'resolved',
                $safe ? 'safe' : 'remain_unavailable', $notes);
            if ($safe) {
                $this->restorePreviouslyDamagedAssets($locked);
            }
        }, 3);
    }

    /**
     * Restore only records marked 'perlu_perbaikan' by the existing return workflow.
     * Manually disabled inventory is never silently re-enabled.
     * Another unresolved safety hold blocks restoration.
     */
    private function restorePreviouslyDamagedAssets(Ticket $ticket): void
    {
        $safety = app(TicketAssetSafetyService::class);
        foreach ($ticket->assets as $link) {
            if ($safety->isBlocked($link->asset_type, $link->asset_id)) {
                continue;
            }
            $model = match ($link->asset_type) {
                'room' => G003M006Room::class,
                'vehicle' => G008M017Vehicle::class,
                'item_instance' => G002M015ItemInstance::class,
                default => null,
            };
            $asset = $model ? $model::query()->lockForUpdate()->find($link->asset_id) : null;
            if ($asset?->status !== 'perlu_perbaikan') {
                continue;
            }
            $values = [
                'status' => $link->asset_type === 'room' ? 'Tersedia' : 'tersedia',
                'is_borrowable' => true,
            ];
            if ($link->asset_type === 'item_instance') {
                $values['is_available'] = true;
            }
            $asset->forceFill($values)->save();
        }
    }

    public function cancel(Ticket $ticket, User $manager, string $notes): void
    {
        if (! $manager->isFacility() || mb_strlen(trim($notes)) < 5) {
            $this->reject('Pembatalan memerlukan fasilitas/admin dan alasan.');
        }
        DB::transaction(function () use ($ticket, $manager, $notes): void {
            $locked = $this->locked($ticket);
            $case = $this->case($locked);
            if (! in_array($locked->status, ['open', 'triaged'], true) || $case->accepted_at) {
                $this->reject('Pekerjaan yang telah dimulai tidak dapat dibatalkan secara langsung.');
            }
            $locked->forceFill(['status' => 'cancelled'])->save();
            $case->forceFill(['asset_released_at' => now(), 'asset_released_by' => $manager->id])->save();
            $this->record($locked, $manager, 'maintenance_cancelled', null, 'cancelled', $notes);
        }, 3);
    }
}
