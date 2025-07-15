<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G008M018Driver extends Model
{
    public function vehicle_reservation(): HasMany
    {
        return $this->hasMany(G005M019VehicleReservation::class, 'g008_m018_driver_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
