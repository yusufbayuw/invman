<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G009M024VehicleChecklist extends Model
{
    protected $casts = [
        'date' => 'date',
        'checklist_date' => 'datetime',
        'is_ok' => 'boolean',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(G008M017Vehicle::class, 'g008_m017_vehicle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
