<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Chatify\Traits\UUID;

class ChFavorite extends Model
{
    /** Explicit mass-assignment allowlist. */
    protected $fillable = [
        'user_id',
        'favorite_id',
    ];

    use UUID;
}
