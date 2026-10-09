<?php

namespace App\Models;

use App\Observers\G002M015ItemInstanceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(G002M015ItemInstanceObserver::class)]
class G002M015ItemInstance extends Model
{
    // Controlled request validation determines writable business fields; primary keys stay guarded.
    protected $guarded = ['id'];

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

    public function item_instance_checklist(): HasMany
    {
        return $this->hasMany(G009M022ItemInstanceChecklist::class, 'g002_m015_item_instance_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(G002M007Item::class, 'g002_m007_item_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(G003M006Room::class, 'g003_m006_room_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }
}
