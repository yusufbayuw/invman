<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class WorkOrderHandlingPlan extends Model
{
    protected $primaryKey = 'ticket_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'ticket_id', 'decision', 'execution_mode', 'vendor_name', 'vendor_scope', 'reason',
        'recorded_by', 'recorded_at', 'acknowledged_by', 'acknowledged_at',
        'vendor_reference', 'vendor_dispatched_at', 'vendor_dispatched_by',
        'vendor_result', 'vendor_evidence', 'vendor_completed_at', 'vendor_completed_by',
        'vendor_accepted', 'vendor_inspection_notes', 'vendor_inspected_at', 'vendor_inspected_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime', 'acknowledged_at' => 'datetime',
        'vendor_dispatched_at' => 'datetime', 'vendor_completed_at' => 'datetime',
        'vendor_inspected_at' => 'datetime', 'vendor_accepted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'plan' => 'Keputusan penanganan dan histori tidak dapat dihapus.',
        ]));
    }

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function acknowledger(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by'); }
}
