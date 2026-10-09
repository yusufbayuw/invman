<?php

namespace App\Models;

use App\Services\RoomQrCodeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class G003M006Room extends Model
{
    // Controlled request validation determines writable business fields; primary keys stay guarded.
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $room): void {
            $room->qr_uuid ??= (string) Str::uuid();
        });

        static::saved(function (self $room): void {
            app(RoomQrCodeService::class)->ensure($room, $room->wasChanged('name'));
        });
    }

    public function publicScheduleUrl(): string
    {
        return route('public.rooms.show', ['room' => $this->qr_uuid]);
    }

    public function qrCodePdfUrl(): string
    {
        return route('public.rooms.qrcode-pdf', ['room' => $this->qr_uuid]);
    }

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

    public function item_management(): BelongsTo
    {
        return $this->belongsTo(G002M003ItemManagement::class, 'g002_m003_item_management_id');
    }
}
