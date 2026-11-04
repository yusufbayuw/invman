<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkOrderApproval extends Model
{
    protected $fillable = [
        'ticket_id', 'work_order_workflow_id', 'step_key', 'label', 'checkpoint',
        'resolver', 'job_title_id', 'target_user_id', 'candidate_user_ids',
        'sequence', 'status', 'decided_by', 'decided_at', 'notes', 'revision', 'superseded_at', 'estimate_id',
    ];

    protected $casts = [
        'candidate_user_ids' => 'array',
        'decided_at' => 'datetime',
        'sequence' => 'integer',
        'revision' => 'integer',
        'superseded_at' => 'datetime',
    ];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function workflow(): BelongsTo { return $this->belongsTo(WorkOrderWorkflow::class, 'work_order_workflow_id'); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }

    protected static function booted(): void
    {
        static::updating(function (self $approval): void {
            $allowed = $approval->getOriginal('superseded_at') ? ['updated_at'] : ['superseded_at', 'updated_at'];
            if (($approval->getOriginal('status') !== 'pending' || $approval->getOriginal('superseded_at'))
                && array_diff(array_keys($approval->getDirty()), $allowed)) {
                throw ValidationException::withMessages(['approval' => 'Keputusan terdahulu tidak dapat ditimpa. Buat revisi yang sah.']);
            }
        });
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'approval' => 'Langkah approval dan audit tidak dapat dihapus.',
        ]));
    }
}
