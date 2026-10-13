<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class TicketMaintenanceCase extends Model
{
    protected $primaryKey = 'ticket_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['ticket_id'];
    protected $casts = [
        'materials' => 'array',
        'approved_at' => 'datetime',
        'accepted_at' => 'datetime',
        'work_finished_at' => 'datetime',
        'verified_at' => 'datetime',
        'final_approved_at' => 'datetime',
        'asset_hold_at' => 'datetime',
        'asset_released_at' => 'datetime',
        'response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'responded_at' => 'datetime',
        'response_escalated_at' => 'datetime',
        'resolution_escalated_at' => 'datetime',
    ];
    protected static function booted(): void
    {
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'maintenance' => 'Dokumen kerja dan histori tidak dapat dihapus.',
        ]));
    }

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function technician(): BelongsTo { return $this->belongsTo(User::class, 'accepted_by'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function finalApprover(): BelongsTo { return $this->belongsTo(User::class, 'final_approved_by'); }
}
