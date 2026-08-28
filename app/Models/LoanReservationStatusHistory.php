<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanReservationStatusHistory extends Model
{
    protected $guarded = [];

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id');
    }
}
