<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class G003M004Building extends Model
{
    /** Explicit, audited mass-assignment allowlist. */
    protected $fillable = [
        'name',
        'location',
        'photo',
    ];

    public function floor(): HasMany
    {
        return $this->hasMany(G003M005Floor::class, 'g003_m004_building_id');
    }
}
