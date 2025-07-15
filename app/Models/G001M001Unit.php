<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class G001M001Unit extends Model
{
    public function user(): HasMany
    {
        return $this->hasMany(User::class, 'g001_m001_unit_id');
    }
    public function room(): HasMany
    {
        return $this->hasMany(G003M006Room::class, 'g001_m001_unit_id');
    }
    public function item(): HasMany
    {
        return $this->hasMany(G002M007Item::class, 'g001_m001_unit_id');
    }
    public function activity(): HasMany
    {
        return $this->hasMany(G004M008Activity::class, 'g001_m001_unit_id');
    }
    public function vehicle(): HasMany
    {
        return $this->hasMany(G008M017Vehicle::class, 'g001_m001_unit_id');
    }
}
