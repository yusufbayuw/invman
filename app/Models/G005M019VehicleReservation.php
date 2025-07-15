<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G005M019VehicleReservation extends Model
{
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(G008M017Vehicle::class, 'g008_m017_vehicle_id');
    }
    public function driver(): BelongsTo
    {
        return $this->belongsTo(G008M018Driver::class, 'g008_m018_driver_id');
    }
}
