<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
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
        $finalSeen = false;
        foreach ($normalized as $step) {
            if ($step['checkpoint'] === 'final') {
                $finalSeen = true;
            } elseif ($finalSeen) {
                throw ValidationException::withMessages(['steps' => 'Persetujuan sebelum pekerjaan harus mendahului pengesahan akhir.']);
            }
        }
        return $normalized;
    }

    /** Both creation and publication serialize on the same stable scope row. */
    public function lockScope(?int $unitId): void
    {
        if ($unitId) {
            \App\Models\G001M001Unit::query()->lockForUpdate()->findOrFail($unitId);
        } else {
            // A global scope has no unit row. Lock one shared principal, not
            // the current administrator (different admins must serialize).
            User::query()->orderBy('id')->lockForUpdate()->firstOrFail();
        }
    }

    public function publish(WorkOrderWorkflow $workflow, User $actor): void
    {
        if (! $actor->is_enabled || ! $actor->isAdmin()) {
            abort(403);
        }
        DB::transaction(function () use ($workflow): void {
            $scope = WorkOrderWorkflow::query()->findOrFail($workflow->id)->g001_m001_unit_id;
            $this->lockScope($scope);
            $locked = WorkOrderWorkflow::query()->lockForUpdate()->findOrFail($workflow->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['workflow' => 'Hanya draft yang dapat dipublikasikan.']);
            }
            if ($locked->g001_m001_unit_id !== $scope
                || WorkOrderWorkflow::query()->where('g001_m001_unit_id', $scope)
                    ->where('status', 'published')->where('version', '>=', $locked->version)->exists()) {
                throw ValidationException::withMessages(['workflow' => 'Draft sudah usang atau versi cakupan ini telah dipublikasikan.']);
            }
            $steps = $this->validateSteps($locked->steps ?? []);
            // Do not activate a role-based workflow with no active person to approve.
            $titleIds = array_values(array_filter(array_unique(array_merge(
                array_column($steps, 'job_title_id'),
                [$locked->assign_job_title_id, $locked->close_job_title_id],
            ))));
            foreach ($titleIds as $titleId) {
                if (! WorkOrderAssignment::query()->where('work_order_job_title_id', $titleId)
                    ->where('is_active', true)
                    ->whereHas('user', fn ($query) => $query->where('is_enabled', true))
                    ->where(function ($query) use ($locked): void {
                        $query->whereNull('g001_m001_unit_id');
                        if ($locked->g001_m001_unit_id) {
                            $query->orWhere('g001_m001_unit_id', $locked->g001_m001_unit_id);
                        }
                    })->exists()) {
                    throw ValidationException::withMessages(['steps' => 'Jabatan approver belum memiliki pegawai aktif sesuai cakupan workflow.']);
                }
            }
            foreach (['assign_job_title_id', 'close_job_title_id'] as $field) {
                if ($locked->{$field} && ! DB::table('work_order_job_titles')->where('id', $locked->{$field})->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages([$field => 'Jabatan yang tidak aktif tidak dapat dipakai.']);
                }
            }
            $locked->forceFill(['steps' => $steps, 'status' => 'published', 'published_at' => now()])->save();
        }, 3);
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
            $candidate = $this->reporterAssignments($ticket)->whereNotNull('supervisor_user_id')
                ->whereHas('supervisor', fn (Builder $query) => $query->where('is_enabled', true))
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
            && in_array($user->id, array_map('intval', $approval->candidate_user_ids ?? []), true)
            && $this->currentlyEligible($approval, $user);
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


    /** Apply the same current authority boundary to list/detail/files as decisions. */
    public function scopeEligibleCandidates(Builder $query, User $user): Builder
    {
        if (! $user->is_enabled) {
            return $query->whereRaw('1=0');
        }
        return $query->where('status', 'pending')
            ->whereJsonContains('candidate_user_ids', (int) $user->id)
            ->whereHas('ticket', fn (Builder $tickets) => $tickets->where(fn (Builder $reporter) =>
                $reporter->whereNull('reporter_id')->orWhere('reporter_id', '!=', $user->id)))
            ->where(function (Builder $candidates) use ($user): void {
                $candidates->where(function (Builder $byTitle) use ($user): void {
                    $byTitle->where('resolver', 'job_title')->whereHas('ticket', function (Builder $tickets) use ($user): void {
                        $tickets->whereExists(function ($assignments) use ($user): void {
                            $assignments->selectRaw('1')->from('work_order_assignments as wo_candidate')
                                ->join('work_order_job_titles as wo_title', 'wo_title.id', '=', 'wo_candidate.work_order_job_title_id')
                                ->where('wo_candidate.user_id', $user->id)->where('wo_candidate.is_active', true)
                                ->where('wo_title.is_active', true)
                                ->whereColumn('wo_candidate.work_order_job_title_id', 'work_order_approvals.job_title_id')
                                ->where(fn ($scope) => $scope->whereNull('wo_candidate.g001_m001_unit_id')
                                    ->orWhereColumn('wo_candidate.g001_m001_unit_id', 'tickets.g001_m001_unit_id'))
                                ->where(fn ($scope) => $scope->whereNull('wo_candidate.g002_m003_item_management_id')
                                    ->orWhereColumn('wo_candidate.g002_m003_item_management_id', 'tickets.g002_m003_item_management_id'));
                        });
                    });
                })->orWhere(function (Builder $bySupervisor) use ($user): void {
                    $bySupervisor->where('resolver', 'reporter_supervisor')->whereHas('ticket', function (Builder $tickets) use ($user): void {
                        $tickets->whereExists(function ($assignments) use ($user): void {
                            $assignments->selectRaw('1')->from('work_order_assignments as wo_reporter')
                                ->join('work_order_job_titles as wo_reporter_title', 'wo_reporter_title.id', '=', 'wo_reporter.work_order_job_title_id')
                                ->whereColumn('wo_reporter.user_id', 'tickets.reporter_id')
                                ->where('wo_reporter.supervisor_user_id', $user->id)
                                ->where('wo_reporter.is_active', true)->where('wo_reporter_title.is_active', true)
                                ->where(fn ($scope) => $scope->whereNull('wo_reporter.g001_m001_unit_id')
                                    ->orWhereColumn('wo_reporter.g001_m001_unit_id', 'tickets.g001_m001_unit_id'))
                                ->where(fn ($scope) => $scope->whereNull('wo_reporter.g002_m003_item_management_id')
                                    ->orWhereColumn('wo_reporter.g002_m003_item_management_id', 'tickets.g002_m003_item_management_id'));
                        });
                    });
                });
            });
    }

    private function reporterAssignments(Ticket $ticket): Builder
    {
        return WorkOrderAssignment::query()->where('user_id', $ticket->reporter_id)
            ->where('is_active', true)->whereHas('jobTitle', fn (Builder $query) => $query->where('is_active', true))
            ->where(fn (Builder $query) => $query->whereNull('g001_m001_unit_id')
                ->when($ticket->g001_m001_unit_id, fn (Builder $scope) => $scope->orWhere('g001_m001_unit_id', $ticket->g001_m001_unit_id)))
            ->where(fn (Builder $query) => $query->whereNull('g002_m003_item_management_id')
                ->when($ticket->g002_m003_item_management_id, fn (Builder $scope) => $scope->orWhere('g002_m003_item_management_id', $ticket->g002_m003_item_management_id)));
    }

    private function currentlyEligible(WorkOrderApproval $approval, User $user): bool
    {
        if ($approval->resolver === 'reporter_supervisor') {
            return $this->reporterAssignments($approval->ticket)->where('supervisor_user_id', $user->id)->exists();
        }
        return $approval->resolver === 'job_title' && $approval->job_title_id
            && $this->hasActiveTitle($approval->ticket, $user, (int) $approval->job_title_id);
    }

    private function hasActiveTitle(Ticket $ticket, User $user, int $titleId): bool
    {
        return WorkOrderAssignment::query()->where('user_id', $user->id)
            ->where('work_order_job_title_id', $titleId)->where('is_active', true)
            ->whereHas('jobTitle', fn ($query) => $query->where('is_active', true))
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
            })->exists();
    }

    public function reassign(WorkOrderApproval $approval, User $admin, User $replacement, string $reason): void
    {
        if (! $admin->isAdmin() || ! $admin->is_enabled) {
            abort(403);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['reason' => 'Alasan pengalihan minimal sepuluh karakter.']);
        }
        DB::transaction(function () use ($approval, $admin, $replacement, $reason): void {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($approval->ticket_id);
            $locked = WorkOrderApproval::query()->lockForUpdate()->findOrFail($approval->id);
            if ($locked->status !== 'pending' || ! $replacement->is_enabled
                || $replacement->id === $ticket->reporter_id) {
                throw ValidationException::withMessages(['approval' => 'Hanya approval tertunda dapat dialihkan kepada pegawai aktif selain pelapor.']);
            }
            $locked->setRelation('ticket', $ticket);
            if (($locked->checkpoint === 'start' && $ticket->status !== 'open')
                || ($locked->checkpoint === 'final' && $ticket->status !== 'resolved')) {
                throw ValidationException::withMessages(['approval' => 'Status tiket tidak cocok untuk pengalihan persetujuan.']);
            }
            if ($locked->resolver === 'job_title' && ! $this->hasActiveTitle($ticket, $replacement, (int) $locked->job_title_id)) {
                throw ValidationException::withMessages(['approval' => 'Pengganti harus memegang jabatan aktif pada cakupan ini.']);
            }
            if ($locked->resolver === 'reporter_supervisor') {
                $locked->setRelation('ticket', $ticket);
                if (! $this->currentlyEligible($locked, $replacement)) {
                    throw ValidationException::withMessages(['approval' => 'Tetapkan pengganti sebagai atasan aktif pelapor terlebih dahulu.']);
                }
            }
            $old = json_encode($locked->candidate_user_ids);
            $locked->forceFill(['candidate_user_ids' => [(int) $replacement->id],
                'target_user_id' => $replacement->id])->save();
            $ticket->events()->create([
                'actor_id' => $admin->id, 'action' => 'work_order_approver_reassigned',
                'from_value' => $locked->step_key, 'to_value' => (string) $replacement->id,
                'notes' => $reason.'; kandidat lama: '.$old,
            ]);
        }, 3);
    }

    public function canPerform(Ticket $ticket, User $user, string $action): bool
    {
        if (! $user->is_enabled || (int) $user->id === (int) $ticket->reporter_id) {
            return false;
        }
        $record = $ticket->workOrderApprovals()->with('workflow')->orderBy('sequence')->first();
        if (! $record) {
            return $user->isFacility();
        }
        $titleId = $action === 'assign' ? $record->workflow->assign_job_title_id : $record->workflow->close_job_title_id;
        if (! $titleId) {
            return $user->isFacility();
        }
        return $user->is_enabled && $user->id !== $ticket->reporter_id
            && $this->hasActiveTitle($ticket, $user, (int) $titleId);
    }
}
