<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasImmutableAuditRecord;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanReservationCorrection extends Model
{
    use HasImmutableAuditRecord;

    /** Explicit mass-assignment allowlist: writes require authorized service flow. */
    protected $fillable = [
        'reservation_type',
        'reservation_id',
        'g004_m008_activity_id',
        'field',
        'old_value',
        'new_value',
        'reason',
        'corrected_by',
    ];

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
