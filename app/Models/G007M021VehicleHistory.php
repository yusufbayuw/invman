<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G007M021VehicleHistory extends Model
{
    /** Explicit, audited mass-assignment allowlist. */
    protected $fillable = [
        'g008_m017_vehicle_id',
        'user_id',
        'action',
        'notes',
        'photo',
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
