<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestChecklist extends Model
{
    /** Explicit mass-assignment allowlist: writes require authorized service flow. */
    protected $fillable = [
        'g004_m008_activity_id',
        'user_id',
        'stage',
        'is_ok',
        'notes',
        'photo',
    ];

    use HasUuids;

    protected $casts = [
        'is_ok' => 'boolean',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
