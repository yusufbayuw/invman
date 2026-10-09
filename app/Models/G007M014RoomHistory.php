<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G007M014RoomHistory extends Model
{
    /** Explicit, audited mass-assignment allowlist. */
    protected $fillable = [
        'g003_m006_room_id',
        'user_id',
        'action',
        'notes',
        'photo',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(G003M006Room::class, 'g003_m006_room_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
