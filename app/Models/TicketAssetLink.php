<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAssetLink extends Model
{
    protected $fillable = ['ticket_id', 'asset_type', 'asset_id'];
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function getAssetNameAttribute(): string
    {
        $class = match ($this->asset_type) {
            'item' => G002M007Item::class,
            'item_instance' => G002M015ItemInstance::class,
            'room' => G003M006Room::class,
            'vehicle' => G008M017Vehicle::class,
            default => null,
        };

        $record = $class ? $class::query()->find($this->asset_id) : null;
        return $record?->name ?? 'Aset tidak tersedia';
    }

}
