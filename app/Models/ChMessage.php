<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Chatify\Traits\UUID;

class ChMessage extends Model
{
    /** Explicit mass-assignment allowlist. */
    protected $fillable = [
        'from_id',
        'to_id',
        'body',
        'attachment',
        'seen',
    ];

    use UUID;
}
