<?php

namespace App\Services;

use App\Models\{Ticket, User, WorkOrderApproval, WorkOrderEstimate};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

final class WorkOrderEstimateService
{
    public function requiresEstimate(WorkOrderApproval $approval): bool
    {
        $step = collect($approval->workflow->steps)->firstWhere('key', $approval->step_key);
        return $approval->checkpoint === 'start' && (bool) ($step['requires_estimate'] ?? false);
    }

    public function current(Ticket $ticket): ?WorkOrderEstimate
    {
        $revision = $ticket->workOrderApprovals()->where('checkpoint', 'start')->min('revision');
        return $revision ? $ticket->workOrderEstimates()->where('revision', $revision)->orderByDesc('version')->first() : null;
    }

    public function canRecord(Ticket $ticket, User $actor): bool
    {
        return $actor->is_enabled && $actor->id !== $ticket->reporter_id
            && $ticket->status === 'open' && $ticket->maintenance && ! $ticket->maintenance->approved_at
            && $ticket->workOrderApprovals()->where('checkpoint', 'start')->exists()
            && ! $ticket->workOrderApprovals()->where('checkpoint', 'start')->where('status', 'rejected')->exists()
            && ! $ticket->workOrderApprovals()->whereNotNull('estimate_id')->where('status', 'approved')->exists()
            && ($actor->isFacility() || app(WorkOrderWorkflowService::class)->canPerform($ticket, $actor, 'assign'));
    }

    public function record(Ticket $ticket, User $actor, array $data, int $expectedVersion): void
    {
        $data = Validator::make($data, [
            'execution_mode' => 'required|in:internal,external',
            'vendor_name' => 'nullable|required_if:execution_mode,external|string|max:200',
            'diagnosis' => 'required|string|min:5|max:10000',
            'amount' => ['required', 'regex:/^(0|[1-9][0-9]{0,11})$/D'],
            'materials' => 'present|array|max:30',
            'materials.*.name' => 'required|string|max:200',
            'materials.*.quantity' => 'required|numeric|gt:0|max:100000',
            'materials.*.unit' => 'required|string|max:25',
        ])->validate();
        DB::transaction(function () use ($ticket, $actor, $data, $expectedVersion): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $actor = User::query()->findOrFail($actor->id);
            $version = (int) $locked->workOrderEstimates()->max('version');
            if (! $this->canRecord($locked, $actor) || $version !== $expectedVersion) {
                throw ValidationException::withMessages(['estimate' => 'Tidak berwenang, estimasi sudah disetujui, atau data telah berubah.']);
            }
            $estimate = $locked->workOrderEstimates()->create([
                'revision' => $locked->workOrderApprovals()->where('checkpoint', 'start')->min('revision'),
                'version' => $version + 1, 'recorded_by' => $actor->id,
                'execution_mode' => $data['execution_mode'],
                'vendor_name' => $data['execution_mode'] === 'external' ? trim($data['vendor_name']) : null,
                'diagnosis' => trim($data['diagnosis']), 'materials' => $data['materials'], 'amount' => $data['amount'],
            ]);
            $locked->events()->create(['actor_id' => $actor->id, 'action' => 'work_order_estimate_recorded',
                'from_value' => (string) $version, 'to_value' => (string) $estimate->version,
                'notes' => 'Estimasi Rp '.$estimate->amount.'; '.$estimate->execution_mode]);
        }, 3);
    }
}
