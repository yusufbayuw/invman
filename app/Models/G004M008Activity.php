<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G004M008Activity extends Model
{
    use HasUuids;

    public function item_reservation(): HasMany
    {
        return $this->hasMany(G005M009ItemReservation::class, 'g004_m008_activity_id');
    }
    public function room_reservation(): HasMany
    {
        return $this->hasMany(G005M010RoomReservation::class, 'g004_m008_activity_id');
    }
    public function vehicle_reservation(): HasMany
    {
        return $this->hasMany(G005M019VehicleReservation::class, 'g004_m008_activity_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }
    
}
