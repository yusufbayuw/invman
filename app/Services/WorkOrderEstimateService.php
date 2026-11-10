<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderEstimate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class WorkOrderEstimateService
{
    public function requiresEstimate(WorkOrderApproval $approval): bool
    {
        $step = collect($approval->workflow->steps)->firstWhere('key', $approval->step_key);

        return $approval->checkpoint === 'start' && (bool) ($step['requires_estimate'] ?? false);
    }

    public function current(Ticket $ticket, bool $lock = false, ?int $revision = null): ?WorkOrderEstimate
    {
        $revision ??= $lock
            ? $ticket->workOrderApprovals()->where('checkpoint', 'start')->orderBy('sequence')->lockForUpdate()->value('revision')
            : $ticket->workOrderApprovals()->where('checkpoint', 'start')->min('revision');

        return $revision ? $ticket->workOrderEstimates()->where('revision', $revision)->orderByDesc('version')->when($lock, fn ($q) => $q->lockForUpdate())->first() : null;
    }

    public function canRecord(Ticket $ticket, User $actor, ?Collection $steps = null, ?WorkOrderAuthoritySnapshot $authority = null): bool
    {
        $steps ??= $ticket->workOrderApprovals()->get();

        return $actor->is_enabled && $actor->id !== $ticket->reporter_id
            && $ticket->status === 'open' && $ticket->maintenance && ! $ticket->maintenance->approved_at
            && $steps->where('checkpoint', 'start')->isNotEmpty()
            && $steps->where('checkpoint', 'start')->where('status', 'rejected')->isEmpty()
            && $steps->whereNotNull('estimate_id')->where('status', 'approved')->isEmpty()
            && ($actor->isFacility() || app(WorkOrderWorkflowService::class)->canPerform($ticket, $actor, 'assign', $authority));
    }

    public function canRecordOverride(Ticket $ticket, User $actor): bool
    {
        $steps = $ticket->workOrderApprovals()->get();
        return $actor->is_enabled && $ticket->status === 'open'
            && $ticket->maintenance && ! $ticket->maintenance->approved_at
            && $steps->where('checkpoint', 'start')->isNotEmpty()
            && $steps->where('checkpoint', 'start')->where('status', 'rejected')->isEmpty()
            && $steps->whereNotNull('estimate_id')->where('status', 'approved')->isEmpty()
            && app(HierarchyOverrideService::class)->can(
                $actor, null, $ticket->g001_m001_unit_id, $ticket->g002_m003_item_management_id);
    }

    public function record(
        Ticket $ticket, User $actor, array $data, int $expectedVersion, ?string $overrideReason = null,
    ): void
    {
        $data = Validator::make($data, [
            'execution_mode' => 'required|in:internal,external,hybrid',
            'vendor_name' => 'nullable|required_if:execution_mode,external,hybrid|string|max:200',
            'diagnosis' => 'required|string|min:5|max:10000',
            'amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,11})$/D'],
            'materials' => 'present|array|max:30',
            'materials.*.name' => 'required|string|max:200',
            'materials.*.quantity' => 'required|numeric|gt:0|max:100000',
            'materials.*.unit' => 'required|string|max:25',
        ])->validate();
        if ($overrideReason !== null) {
            $overrideReason = app(HierarchyOverrideService::class)->validateReason($overrideReason);
        }
        DB::transaction(function () use ($ticket, $actor, $data, $expectedVersion, $overrideReason): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $steps = $locked->workOrderApprovals()->lockForUpdate()->get();
            $authority = WorkOrderAuthoritySnapshot::lock([$actor->id, $locked->reporter_id]);
            $actor = $authority->user($actor->id);
            $version = (int) $locked->workOrderEstimates()->orderByDesc('version')->lockForUpdate()->value('version');
            $allowed = $overrideReason !== null
                ? $this->canRecordOverride($locked, $actor)
                : $this->canRecord($locked, $actor, $steps, $authority);
            if (! $allowed || $version !== $expectedVersion) {
                throw ValidationException::withMessages(['estimate' => 'Tidak berwenang, estimasi sudah disetujui, atau data telah berubah.']);
            }
            $estimate = $locked->workOrderEstimates()->create([
                'revision' => $steps->where('checkpoint', 'start')->min('revision'),
                'version' => $version + 1, 'recorded_by' => $actor->id,
                'execution_mode' => $data['execution_mode'],
                'vendor_name' => $data['execution_mode'] !== 'internal' ? trim($data['vendor_name']) : null,
                'diagnosis' => trim($data['diagnosis']), 'materials' => $data['materials'], 'amount' => $data['amount'],
            ]);
            if ($overrideReason !== null) {
                app(HierarchyOverrideService::class)->record(
                    $actor, null, 'ticket', $locked->id, 'work_order_estimate',
                    $overrideReason, (string) $version, (string) $estimate->version,
                    ['amount' => $estimate->amount, 'self_approval' => $actor->id === $locked->reporter_id],
                );
            }
            $locked->events()->create(['actor_id' => $actor->id,
                'action' => $overrideReason !== null ? 'work_order_estimate_overridden' : 'work_order_estimate_recorded',
                'from_value' => (string) $version, 'to_value' => (string) $estimate->version,
                'notes' => 'Estimasi Rp '.$estimate->amount.'; '.$estimate->execution_mode]);
        }, 3);
    }
}
