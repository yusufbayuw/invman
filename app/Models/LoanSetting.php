<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanSetting extends Model
{
    /** Explicit mass-assignment allowlist. */
    protected $fillable = [
        'key',
        'value',
    ];
}
