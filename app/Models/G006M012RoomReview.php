<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G006M012RoomReview extends Model
{
    public function room_reservation(): BelongsTo
    {
        return $this->belongsTo(G005M010RoomReservation::class, 'g005_m010_room_reservation_id');
    }
}
