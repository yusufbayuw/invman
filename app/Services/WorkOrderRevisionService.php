<?php

namespace App\Services;

use App\Models\{Ticket, TicketMaintenanceCase, User, WorkOrderApproval};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

final class WorkOrderRevisionService
{
    public function canRevise(Ticket $ticket, User $actor, string $checkpoint): bool
    {
        if (! $actor->is_enabled || ! $ticket->maintenance
            || ! $ticket->workOrderApprovals()->where('checkpoint', $checkpoint)->where('status', 'rejected')->exists()) {
            return false;
        }
        return $checkpoint === 'start'
            ? $ticket->status === 'open' && ! $ticket->maintenance->approved_at && $ticket->reporter_id === $actor->id
            : $checkpoint === 'final' && $ticket->status === 'resolved' && ! $ticket->maintenance->final_approved_at
                && $ticket->assigned_to === $actor->id && \App\Models\TicketTeamMember::qualifiesFor($ticket, $actor);
    }

    /** Start: reporter corrects the report. Final: assignee reworks and obtains fresh verification. */
    public function resubmit(Ticket $ticket, User $actor, int $rejectionId, string $reason, array $report = []): void
    {
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:10|max:2000'])->validate();
        DB::transaction(function () use ($ticket, $actor, $rejectionId, $reason, $report): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $actor = User::query()->findOrFail($actor->id);
            $case = TicketMaintenanceCase::query()->lockForUpdate()->findOrFail($locked->id);
            $locked->setRelation('maintenance', $case);
            $rejected = $locked->workOrderApprovals()->whereKey($rejectionId)->where('status', 'rejected')->first();
            if (! $rejected || ! $this->canRevise($locked, $actor, $rejected->checkpoint)) {
                throw ValidationException::withMessages(['revision' => 'Penolakan sudah berubah atau Anda tidak berwenang merevisi tahap ini.']);
            }
            $checkpoint = $rejected->checkpoint;
            $fields = ['title', 'description', 'location', 'reported_urgency'];
            if ($checkpoint === 'start') {
                if (array_diff(array_keys($report), $fields)) {
                    throw ValidationException::withMessages(['revision' => 'Hanya isi laporan yang boleh direvisi.']);
                }
                $report = Validator::make($report, [
                    'title' => 'required|string|min:5|max:255',
                    'description' => 'required|string|min:10|max:10000',
                    'location' => 'required|string|max:255',
                    'reported_urgency' => 'required|in:normal,urgent,emergency',
                ])->validate();
                $before = $locked->only($fields);
                $locked->fill($report);
                if (! $locked->isDirty($fields)) {
                    throw ValidationException::withMessages(['revision' => 'Perbaiki isi laporan sesuai alasan penolakan.']);
                }
                $locked->save();
                $after = $locked->only($fields);
            } else {
                if ($report !== []) {
                    throw ValidationException::withMessages(['revision' => 'Teknisi tidak boleh mengubah laporan pelapor.']);
                }
                $before = $case->only(['diagnosis', 'repair_actions', 'materials', 'outcome', 'work_finished_at', 'verified_by', 'verified_at']);
                $case->forceFill(['work_finished_at' => null, 'verified_by' => null, 'verified_at' => null])->save();
                $locked->forceFill(['status' => 'in_progress', 'resolved_at' => null])->save();
                $after = ['status' => 'in_progress', 'requires_new_result_and_verification' => true];
            }
            // Preserve the workflow version, candidate snapshot and all prior decisions.
            // A changed report invalidates every approval; rework invalidates final approvals only.
            $steps = $locked->workOrderApprovals()
                ->when($checkpoint === 'final', fn ($query) => $query->where('checkpoint', 'final'))
                ->lockForUpdate()->get();
            $revision = (int) $locked->workOrderApprovalHistory()->max('revision') + 1;
            foreach ($steps as $step) {
                $step->forceFill(['superseded_at' => now()])->save();
                $next = $step->replicate(['decided_by', 'decided_at', 'notes', 'superseded_at', 'estimate_id']);
                $next->forceFill(['revision' => $revision, 'status' => 'pending'])->save();
            }
            $locked->workOrderRevisions()->create([
                'rejected_approval_id' => $rejectionId, 'actor_id' => $actor->id,
                'revision' => $revision, 'checkpoint' => $checkpoint, 'reason' => $reason,
                'before' => $before, 'after' => $after,
            ]);
            $locked->events()->create([
                'actor_id' => $actor->id, 'action' => 'work_order_revision_submitted',
                'from_value' => (string) $rejectionId, 'to_value' => (string) $revision, 'notes' => $reason,
            ]);
        }, 3);
    }
}
