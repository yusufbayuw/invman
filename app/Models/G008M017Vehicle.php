<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G008M017Vehicle extends Model
{
    public function vehicle_reservation(): HasMany
    {
        return $this->hasMany(G005M019VehicleReservation::class, 'g008_m017_vehicle_id');
    }
    public function vehicle_review(): HasMany
    {
        return $this->hasMany(G006M020VehicleReview::class, 'g008_m017_vehicle_id');
    }
    public function vehicle_history(): HasMany
    {
        return $this->hasMany(G007M021VehicleHistory::class, 'g008_m017_vehicle_id');
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }
}
