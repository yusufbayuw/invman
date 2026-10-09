<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanHandoverReceipt extends Model
{
    use HasUuids;

    /** Explicit mass-assignment allowlist: writes require authorized service flow. */
    protected $fillable = [
        'reservation_type',
        'reservation_id',
        'g004_m008_activity_id',
        'direction',
        'initiated_by',
        'borrower_confirmed_by',
        'borrower_confirmed_at',
        'manager_confirmed_by',
        'manager_confirmed_at',
        'proof_path',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'borrower_confirmed_at' => 'datetime',
        'manager_confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id');
    }
}
