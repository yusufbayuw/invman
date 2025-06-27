<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G005M016ItemReservationDetail extends Model
{
    public function item_reservation(): BelongsTo
    {
        return $this->belongsTo(G005M009ItemReservation::class, 'g005_m009_item_reservation_id');
    }
    public function item_instance(): BelongsTo
    {
        return $this->belongsTo(G002M015ItemInstance::class, 'g002_m015_item_instance_id');
    }
}
