<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanReservationChecklist extends Model
{
    use HasUuids;

    /** Explicit mass-assignment allowlist: writes require authorized service flow. */
    protected $fillable = [
        'reservation_type',
        'reservation_id',
        'g004_m008_activity_id',
        'g002_m015_item_instance_id',
        'checked_by',
        'is_ok',
        'notes',
        'photo',
        'checked_at',
    ];

    protected $casts = [
        'is_ok' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function itemInstance(): BelongsTo
    {
        return $this->belongsTo(G002M015ItemInstance::class, 'g002_m015_item_instance_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
