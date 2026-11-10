<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrderHandlingPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A handling decision is not a technician's completion report.
 * No-repair decisions use separate acknowledgement and final authorization;
 * they never fabricate work_finished_at, verified_at or material usage.
 */
final class WorkOrderHandlingService
{
    public const NON_REPAIR = ['replace', 'not_repairable'];

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['handling' => $message]);
    }

    private function authorizeManager(Ticket $ticket, User $actor, string $action = 'assign'): void
    {
        if (! app(WorkOrderWorkflowService::class)->canPerform($ticket, $actor, $action)) {
            $this->reject('Anda tidak berwenang mengambil keputusan penanganan tiket ini.');
        }
    }

    private function audit(Ticket $ticket, User $actor, string $event, ?string $from, ?string $to, ?string $notes): void
    {
        $ticket->events()->create([
            'actor_id' => $actor->id, 'action' => $event,
            'from_value' => $from, 'to_value' => $to, 'notes' => $notes,
        ]);
    }

    public function recordPlan(
        Ticket $ticket, User $actor, string $decision, ?string $mode, ?string $vendorName,
        ?string $vendorScope, string $reason,
    ): WorkOrderHandlingPlan {
        $reason = trim($reason);
        $vendorName = trim((string) $vendorName);
        $vendorScope = trim((string) $vendorScope);
        if (! in_array($decision, ['repair', 'replace', 'not_repairable', 'defer'], true)
            || mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            $this->reject('Keputusan dan alasan minimal sepuluh karakter wajib diisi.');
        }
        if ($decision === 'repair' && ! in_array($mode, ['internal', 'external', 'hybrid'], true)) {
            $this->reject('Pilih pelaksanaan oleh tim internal, vendor, atau gabungan.');
        }
        if ($decision === 'repair' && in_array($mode, ['external', 'hybrid'], true)
            && (mb_strlen($vendorName) < 2 || mb_strlen($vendorName) > 200
                || mb_strlen($vendorScope) < 10 || mb_strlen($vendorScope) > 5000)) {
            $this->reject('Nama vendor dan lingkup servis yang jelas wajib diisi.');
        }

        return DB::transaction(function () use ($ticket, $actor, $decision, $mode, $vendorName, $vendorScope, $reason): WorkOrderHandlingPlan {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorizeManager($locked, $actor);
            if ($locked->status !== 'open' || ! $locked->maintenance || $locked->maintenance->approved_at
                || $locked->workOrderApprovals()->where('checkpoint', 'start')->where('status', 'approved')->exists()) {
                $this->reject('Rencana hanya dapat diubah sebelum persetujuan awal atau penugasan dimulai.');
            }
            $plan = WorkOrderHandlingPlan::query()->whereKey($locked->id)->lockForUpdate()->first();
            if ($plan?->acknowledged_at) {
                $this->reject('Keputusan yang sudah dikonfirmasi pelapor tidak boleh diganti tanpa revisi formal.');
            }
            $from = $plan?->decision;
            $values = [
                'decision' => $decision,
                'execution_mode' => $decision === 'repair' ? $mode : null,
                'vendor_name' => $decision === 'repair' && $mode !== 'internal' ? $vendorName : null,
                'vendor_scope' => $decision === 'repair' && $mode !== 'internal' ? $vendorScope : null,
                'reason' => $reason, 'recorded_by' => $actor->id, 'recorded_at' => now(),
            ];
            $plan = WorkOrderHandlingPlan::query()->updateOrCreate(['ticket_id' => $locked->id], $values);
            $this->audit($locked, $actor, 'work_order_handling_decided', $from, $decision,
                $reason.'; pelaksana: '.($values['execution_mode'] ?? 'tidak ada'));
            return $plan;
        }, 3);
    }

    public function acknowledgeNoRepair(Ticket $ticket, User $reporter): void
    {
        DB::transaction(function () use ($ticket, $reporter): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $plan = WorkOrderHandlingPlan::query()->whereKey($locked->id)->lockForUpdate()->first();
            if (! $reporter->is_enabled || $locked->status !== 'open'
                || (int) $locked->reporter_id !== (int) $reporter->id
                || ! $plan || ! in_array($plan->decision, self::NON_REPAIR, true)
                || $plan->acknowledged_at) {
                $this->reject('Konfirmasi hanya untuk pelapor tiket yang diputuskan tidak diperbaiki.');
            }
            $plan->forceFill(['acknowledged_by' => $reporter->id, 'acknowledged_at' => now()])->save();
            $this->audit($locked, $reporter, 'work_order_no_repair_acknowledged', null, $plan->decision,
                'Pelapor mengetahui keputusan; bukan verifikasi pekerjaan selesai.');
        }, 3);
    }

    public function closeNoRepair(Ticket $ticket, User $manager, string $notes): void
    {
        if (mb_strlen(trim($notes)) < 10 || mb_strlen($notes) > 2000) {
            $this->reject('Alasan penutupan administratif minimal sepuluh karakter.');
        }
        DB::transaction(function () use ($ticket, $manager, $notes): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorizeManager($locked, $manager, 'close');
            $plan = WorkOrderHandlingPlan::query()->whereKey($locked->id)->lockForUpdate()->first();
            if ($locked->status !== 'open' || $locked->assigned_to || ! $locked->maintenance
                || $locked->maintenance->approved_at || ! $plan
                || ! in_array($plan->decision, self::NON_REPAIR, true) || ! $plan->acknowledged_at) {
                $this->reject('Penutupan tanpa perbaikan harus disetujui pelapor dan belum menjadi pekerjaan teknisi.');
            }
            $workflow = app(WorkOrderWorkflowService::class);
            $workflow->assertReady($locked, 'start');
            $workflow->assertReady($locked, 'final');
            $locked->maintenance->forceFill([
                'outcome' => 'unrepairable',
                'closure_notes' => trim($notes),
                'final_approved_by' => $manager->id,
                'final_approved_at' => now(),
            ])->save();
            $locked->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
            // An attached asset stays held: no safety clearance was performed.
            $this->audit($locked, $manager, 'work_order_closed_without_repair',
                'open', 'closed', $plan->decision.'; '.trim($notes));
        }, 3);
    }

    public function dispatchVendor(Ticket $ticket, User $actor, string $reference): void
    {
        if (mb_strlen(trim($reference)) < 5 || mb_strlen($reference) > 200) {
            $this->reject('Nomor referensi servis minimal lima karakter.');
        }
        DB::transaction(function () use ($ticket, $actor, $reference): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorizeManager($locked, $actor);
            $plan = WorkOrderHandlingPlan::query()->whereKey($locked->id)->lockForUpdate()->first();
            if (! $plan || $plan->decision !== 'repair'
                || ! in_array($plan->execution_mode, ['external', 'hybrid'], true)
                || ! $locked->maintenance?->approved_at
                || ! in_array($locked->status, ['triaged', 'in_progress', 'waiting_parts'], true)
                || $plan->vendor_dispatched_at) {
                $this->reject('Penyerahan pekerjaan ke vendor tidak sesuai rencana atau tahap saat ini.');
            }
            $plan->forceFill([
                'vendor_reference' => trim($reference),
                'vendor_dispatched_at' => now(), 'vendor_dispatched_by' => $actor->id,
            ])->save();
            $this->audit($locked, $actor, 'work_order_vendor_dispatched', null, trim($reference), $plan->vendor_name);
        }, 3);
    }

    public function vendorResult(Ticket $ticket, User $actor, string $result, string $evidence): void
    {
        if (mb_strlen(trim($result)) < 10 || mb_strlen($result) > 5000
            || mb_strlen(trim($evidence)) < 5 || mb_strlen($evidence) > 2000) {
            $this->reject('Hasil servis dan referensi bukti wajib terisi dengan jelas.');
        }
        DB::transaction(function () use ($ticket, $actor, $result, $evidence): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorizeManager($locked, $actor);
            $plan = WorkOrderHandlingPlan::query()->whereKey($locked->id)->lockForUpdate()->first();
            if (! $plan?->vendor_dispatched_at
                || ! in_array($locked->status, ['triaged', 'in_progress', 'waiting_parts'], true)
                || ($plan->vendor_completed_at && $plan->vendor_accepted)) {
                $this->reject('Belum ada servis yang dapat dicatat atau servis telah diterima.');
            }
            $plan->forceFill([
                'vendor_result' => trim($result), 'vendor_evidence' => trim($evidence),
                'vendor_completed_at' => now(), 'vendor_completed_by' => $actor->id,
                'vendor_accepted' => null, 'vendor_inspection_notes' => null,
                'vendor_inspected_at' => null, 'vendor_inspected_by' => null,
            ])->save();
            $this->audit($locked, $actor, 'work_order_vendor_result_submitted',
                $plan->vendor_reference, 'needs_inspection', trim($result));
        }, 3);
    }

    public function inspectVendor(Ticket $ticket, User $actor, bool $accepted, string $notes): void
    {
        if (mb_strlen(trim($notes)) < 10 || mb_strlen($notes) > 2000) {
            $this->reject('Catatan pemeriksaan servis minimal sepuluh karakter.');
        }
        DB::transaction(function () use ($ticket, $actor, $accepted, $notes): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorizeManager($locked, $actor);
            $plan = WorkOrderHandlingPlan::query()->whereKey($locked->id)->lockForUpdate()->first();
            if (! $plan?->vendor_completed_at || $plan->vendor_inspected_at
                || $plan->vendor_completed_by === $actor->id
                || ! in_array($locked->status, ['triaged', 'in_progress', 'waiting_parts'], true)) {
                $this->reject('Pemeriksaan harus oleh petugas berbeda setelah hasil servis diserahkan.');
            }
            $plan->forceFill([
                'vendor_accepted' => $accepted, 'vendor_inspection_notes' => trim($notes),
                'vendor_inspected_by' => $actor->id, 'vendor_inspected_at' => now(),
            ])->save();
            $this->audit($locked, $actor, 'work_order_vendor_inspected',
                'needs_inspection', $accepted ? 'accepted' : 'rejected', trim($notes));
        }, 3);
    }

    public function assertReadyForCompletion(Ticket $ticket): void
    {
        $plan = $ticket->handlingPlan;
        if ($plan && $plan->decision !== 'repair') {
            $this->reject('Keputusan penanganan bukan perbaikan. Gunakan alur tindak lanjut administratif.');
        }
        if ($plan && in_array($plan->execution_mode, ['external', 'hybrid'], true)
            && (! $plan->vendor_dispatched_at || ! $plan->vendor_inspected_at || ! $plan->vendor_accepted)) {
            $this->reject('Hasil pekerjaan vendor belum diserahkan dan lolos pemeriksaan.');
        }
    }
}
