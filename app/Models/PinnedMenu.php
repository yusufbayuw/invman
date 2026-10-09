<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PinnedMenu extends Model
{
    /** Explicit mass-assignment allowlist. */
    protected $fillable = [
        'user_id',
        'label',
        'url',
        'icon',
    ];

    //
}
