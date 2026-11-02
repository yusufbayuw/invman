<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WorkOrderWorkflowService
{
    public function current(?int $unitId): ?WorkOrderWorkflow
    {
        return WorkOrderWorkflow::query()->where('status', 'published')
            ->where(function ($query) use ($unitId): void {
                $query->where('g001_m001_unit_id', $unitId);
            })->orderByDesc('version')->first()
            ?? WorkOrderWorkflow::query()->where('status', 'published')
                ->whereNull('g001_m001_unit_id')->orderByDesc('version')->first();
    }

    public function validateSteps(array $steps): array
    {
        if (count($steps) < 1 || count($steps) > 12) {
            throw ValidationException::withMessages(['steps' => 'Buat 1 sampai 12 langkah persetujuan.']);
        }
        $seen = [];
        $normalized = [];
        foreach ($steps as $step) {
            $key = trim((string) ($step['key'] ?? ''));
            $label = trim((string) ($step['label'] ?? ''));
            $checkpoint = $step['checkpoint'] ?? null;
            $resolver = $step['resolver'] ?? null;
            $titleId = filled($step['job_title_id'] ?? null) ? (int) $step['job_title_id'] : null;
            if (! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $key) || isset($seen[$key])
                || $label === '' || mb_strlen($label) > 150
                || ! in_array($checkpoint, ['start', 'final'], true)
                || ! in_array($resolver, ['job_title', 'reporter_supervisor'], true)
                || ($resolver === 'job_title' && (! $titleId || ! DB::table('work_order_job_titles')->where('id', $titleId)->where('is_active', true)->exists()))) {
                throw ValidationException::withMessages(['steps' => 'Langkah workflow tidak valid, duplikat, atau jabatan tidak aktif.']);
            }
            $seen[$key] = true;
            $normalized[] = compact('key', 'label', 'checkpoint', 'resolver') + ['job_title_id' => $resolver === 'job_title' ? $titleId : null];
        }
        if (collect($normalized)->pluck('checkpoint')->contains('start')
            && collect($normalized)->pluck('checkpoint')->contains('final')
            && array_search('final', array_column($normalized, 'checkpoint'), true) < array_search('start', array_column($normalized, 'checkpoint'), true)) {
            throw ValidationException::withMessages(['steps' => 'Persetujuan sebelum pekerjaan harus mendahului pengesahan akhir.']);
        }
        return $normalized;
    }

    public function publish(WorkOrderWorkflow $workflow, User $actor): void
    {
        if (! $actor->isAdmin()) {
            abort(403);
        }
        DB::transaction(function () use ($workflow): void {
            $locked = WorkOrderWorkflow::query()->lockForUpdate()->findOrFail($workflow->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['workflow' => 'Hanya draft yang dapat dipublikasikan.']);
            }
            $steps = $this->validateSteps($locked->steps ?? []);
            foreach (['assign_job_title_id', 'close_job_title_id'] as $field) {
                if ($locked->{$field} && ! DB::table('work_order_job_titles')->where('id', $locked->{$field})->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([$field => 'Jabatan yang tidak aktif tidak dapat dipakai.']);
                }
            }
            $locked->forceFill(['steps' => $steps, 'status' => 'published', 'published_at' => now()])->save();
        });
    }

    public function snapshot(Ticket $ticket): void
    {
        $workflow = $this->current($ticket->g001_m001_unit_id);
        if (! $workflow) {
            return;
        }
        foreach ($workflow->steps as $sequence => $step) {
            $candidates = $this->resolveCandidates($ticket, $step);
            WorkOrderApproval::query()->firstOrCreate([
                'ticket_id' => $ticket->id, 'step_key' => $step['key'],
            ], [
                'work_order_workflow_id' => $workflow->id,
                'label' => $step['label'],
                'checkpoint' => $step['checkpoint'],
                'resolver' => $step['resolver'],
                'job_title_id' => $step['job_title_id'] ?? null,
                'target_user_id' => $step['resolver'] === 'reporter_supervisor' ? ($candidates[0] ?? null) : null,
                'candidate_user_ids' => $candidates,
                'sequence' => $sequence,
                'status' => 'pending',
            ]);
        }
    }

    private function resolveCandidates(Ticket $ticket, array $step): array
    {
        if ($step['resolver'] === 'reporter_supervisor') {
            $candidate = WorkOrderAssignment::query()->where('user_id', $ticket->reporter_id)
                ->where('is_active', true)->whereNotNull('supervisor_user_id')
                ->when($ticket->g001_m001_unit_id, fn ($query) => $query->where('g001_m001_unit_id', $ticket->g001_m001_unit_id))
                ->orderBy('id')->value('supervisor_user_id');
            return $candidate ? [(int) $candidate] : [];
        }
        return WorkOrderAssignment::query()->where('work_order_job_title_id', $step['job_title_id'])
            ->where('is_active', true)
            ->where(function ($query) use ($ticket): void {
                $query->whereNull('g001_m001_unit_id');
                if ($ticket->g001_m001_unit_id) {
                    $query->orWhere('g001_m001_unit_id', $ticket->g001_m001_unit_id);
                }
            })->where(function ($query) use ($ticket): void {
                $query->whereNull('g002_m003_item_management_id');
                if ($ticket->g002_m003_item_management_id) {
                    $query->orWhere('g002_m003_item_management_id', $ticket->g002_m003_item_management_id);
                }
            })->whereHas('user', fn ($query) => $query->where('is_enabled', true))
            ->pluck('user_id')->unique()->map(fn ($id): int => (int) $id)->values()->all();
    }

    public function required(Ticket $ticket, string $checkpoint): bool
    {
        return $ticket->workOrderApprovals()->where('checkpoint', $checkpoint)->exists();
    }

    public function next(Ticket $ticket, string $checkpoint): ?WorkOrderApproval
    {
        return $ticket->workOrderApprovals()->where('checkpoint', $checkpoint)
            ->where('status', 'pending')->orderBy('sequence')->first();
    }

    public function canDecide(WorkOrderApproval $approval, User $user): bool
    {
        if (! $user->is_enabled || $approval->status !== 'pending') {
            return false;
        }
        if ($approval->checkpoint === 'start' && $approval->ticket->status !== 'open') {
            return false;
        }
        if ($approval->checkpoint === 'final' && ($approval->ticket->status !== 'resolved'
            || ! $approval->ticket->maintenance?->verified_at)) {
            return false;
        }
        if ($approval->ticket->workOrderApprovals()->where('checkpoint', $approval->checkpoint)->where('status', 'rejected')->exists()) {
            return false;
        }
        $first = $this->next($approval->ticket, $approval->checkpoint);
        if (! $first || $first->id !== $approval->id) {
            return false;
        }
        return $user->id !== $approval->ticket->reporter_id
            && in_array($user->id, array_map('intval', $approval->candidate_user_ids ?? []), true);
    }

    public function decide(WorkOrderApproval $approval, User $user, bool $approved, ?string $notes): void
    {
        DB::transaction(function () use ($approval, $user, $approved, $notes): void {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($approval->ticket_id);
            $locked = WorkOrderApproval::query()->lockForUpdate()->findOrFail($approval->id);
            $locked->setRelation('ticket', $ticket);
            if (! $this->canDecide($locked, $user)) {
                throw ValidationException::withMessages(['approval' => 'Tidak berwenang atau bukan tahap persetujuan yang aktif.']);
            }
            if (! $approved && mb_strlen(trim((string) $notes)) < 5) {
                throw ValidationException::withMessages(['notes' => 'Alasan penolakan minimal lima karakter.']);
            }
            $locked->forceFill([
                'status' => $approved ? 'approved' : 'rejected',
                'decided_by' => $user->id,
                'decided_at' => now(),
                'notes' => $notes,
            ])->save();
            $ticket->events()->create([
                'actor_id' => $user->id,
                'action' => $approved ? 'work_order_approval_granted' : 'work_order_approval_rejected',
                'from_value' => $locked->step_key,
                'to_value' => $locked->status,
                'notes' => $notes,
            ]);
        }, 3);
    }

    public function assertReady(Ticket $ticket, string $checkpoint): void
    {
        if ($ticket->workOrderApprovals()->where('checkpoint', $checkpoint)->where('status', 'rejected')->exists()
            || $ticket->workOrderApprovals()->where('checkpoint', $checkpoint)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['approval' => 'Tahap persetujuan Work Order belum selesai atau ditolak.']);
        }
    }

    public function canPerform(Ticket $ticket, User $user, string $action): bool
    {
        $record = $ticket->workOrderApprovals()->with('workflow')->orderBy('sequence')->first();
        if (! $record) {
            return $user->isFacility();
        }
        $titleId = $action === 'assign' ? $record->workflow->assign_job_title_id : $record->workflow->close_job_title_id;
        if (! $titleId) {
            return $user->isFacility();
        }
        return $user->is_enabled && $user->id !== $ticket->reporter_id
            && WorkOrderAssignment::query()->where('user_id', $user->id)->where('work_order_job_title_id', $titleId)
                ->where('is_active', true)
                ->where(function ($q) use ($ticket): void {
                    $q->whereNull('g001_m001_unit_id');
                    if ($ticket->g001_m001_unit_id) {
                        $q->orWhere('g001_m001_unit_id', $ticket->g001_m001_unit_id);
                    }
                })->where(function ($q) use ($ticket): void {
                    $q->whereNull('g002_m003_item_management_id');
                    if ($ticket->g002_m003_item_management_id) {
                        $q->orWhere('g002_m003_item_management_id', $ticket->g002_m003_item_management_id);
                    }
                })->exists();
    }
}
