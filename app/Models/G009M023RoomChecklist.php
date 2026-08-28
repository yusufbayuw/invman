<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G009M023RoomChecklist extends Model
{
    protected $casts = [
        'date' => 'date',
        'checklist_date' => 'datetime',
        'is_ok' => 'boolean',
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
