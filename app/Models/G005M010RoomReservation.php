<?php

namespace App\Models;

use App\Observers\G005M010RoomReservationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(G005M010RoomReservationObserver::class)]
class G005M010RoomReservation extends Model
{
    use HasUuids;

    public function room_review(): HasMany
    {
        return $this->hasMany(G006M012RoomReview::class, 'g005_m010_room_reservation_id');
    }
    public function activity(): BelongsTo
    {
        return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id');
    }
    public function room(): BelongsTo
    {
        return $this->belongsTo(G003M006Room::class, 'g003_m006_room_id');
    }
}
