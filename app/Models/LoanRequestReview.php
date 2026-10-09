<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRequestReview extends Model
{
    /** Explicit mass-assignment allowlist: writes require authorized service flow. */
    protected $fillable = [
        'g004_m008_activity_id',
        'user_id',
        'rating',
        'review',
    ];

    use HasUuids;

    protected $casts = [
        'rating' => 'integer',
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
