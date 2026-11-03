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
        'sequence', 'status', 'decided_by', 'decided_at', 'notes',
    ];

    protected $casts = [
        'candidate_user_ids' => 'array',
        'decided_at' => 'datetime',
        'sequence' => 'integer',
    ];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function workflow(): BelongsTo { return $this->belongsTo(WorkOrderWorkflow::class, 'work_order_workflow_id'); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class, 'decided_by'); }

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'approval' => 'Langkah approval dan audit tidak dapat dihapus.',
        ]));
    }
}
