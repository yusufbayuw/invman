<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G002M015ItemInstance extends Model
{
    public function item_review(): HasMany
    {
        return $this->hasMany(G006M011ItemReview::class, 'g002_m015_item_instance_id');
    }
    public function item_history(): HasMany
    {
        return $this->hasMany(G007M013ItemHistory::class, 'g002_m015_item_instance_id');
    }
    public function item_reservation_detail(): HasMany
    {
        return $this->hasMany(G005M016ItemReservationDetail::class, 'g002_m015_item_instance_id');
    }
    public function item(): BelongsTo
    {
        return $this->belongsTo(G002M007Item::class, 'g002_m007_item_id');
    }
}
