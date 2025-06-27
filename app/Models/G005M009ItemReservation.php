<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G005M009ItemReservation extends Model
{
    use HasUuids;

    public function item_reservation_detail(): HasMany
    {
        return $this->hasMany(G005M016ItemReservationDetail::class, 'g005_m009_item_reservation_id');
    }
    public function activity(): BelongsTo
    {
        return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id');
    }
    public function item(): BelongsTo
    {
        return $this->belongsTo(G002M007Item::class, 'g002_m007_item_id');
    }
}
