<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanHandoverReceipt extends Model
{
    use HasUuids;

    protected $guarded = [];

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
