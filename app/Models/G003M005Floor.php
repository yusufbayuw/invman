<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G003M005Floor extends Model
{
    public function room(): HasMany
    {
        return $this->hasMany(G003M006Room::class, 'g003_m005_floor_id');
    }
    public function building(): BelongsTo
    {
        return $this->belongsTo(G003M004Building::class, 'g003_m004_building_id');
    }
}
