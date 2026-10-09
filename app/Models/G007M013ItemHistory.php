<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G007M013ItemHistory extends Model
{
    /** Explicit, audited mass-assignment allowlist. */
    protected $fillable = [
        'g002_m015_item_instance_id',
        'user_id',
        'action',
        'notes',
        'photo',
    ];

    public function item_instance(): BelongsTo
    {
        return $this->belongsTo(G002M015ItemInstance::class, 'g002_m015_item_instance_id');
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
