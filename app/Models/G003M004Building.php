<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class G003M004Building extends Model
{
    public function floor(): HasMany
    {
        return $this->hasMany(G003M005Floor::class, 'g003_m004_building_id');
    }
}
