<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G003M006Room extends Model
{
    public function item(): HasMany
    {
        return $this->hasMany(G002M007Item::class, 'g003_m006_room_id');
    }
    public function room_reservation(): HasMany
    {
        return $this->hasMany(G005M010RoomReservation::class, 'g003_m006_room_id');
    }
    public function room_history(): HasMany
    {
        return $this->hasMany(G007M014RoomHistory::class, 'g003_m006_room_id');
    }
    public function floor(): BelongsTo
    {
        return $this->belongsTo(G003M005Floor::class, 'g003_m005_floor_id');
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }
}
