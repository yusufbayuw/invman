<?php

namespace App\Models;

use App\Observers\G002M007ItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(G002M007ItemObserver::class)]
class G002M007Item extends Model
{
    public function item_reservation(): HasMany
    {
        return $this->hasMany(G005M009ItemReservation::class, 'g002_m007_item_id');
    }
    public function item_instance(): HasMany
    {
        return $this->hasMany(G002M015ItemInstance::class, 'g002_m007_item_id');
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }
    public function item_management(): BelongsTo
    {
        return $this->belongsTo(G002M003ItemManagement::class, 'g002_m003_item_management_id');
    }
    public function item_type(): BelongsTo
    {
        return $this->belongsTo(G002M002ItemType::class, 'g002_m002_item_type_id');
    }
    public function room(): BelongsTo
    {
        return $this->belongsTo(G003M006Room::class, 'g003_m006_room_id');
    }
}
