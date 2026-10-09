<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class G002M003ItemManagement extends Model
{
    /** Explicit, audited mass-assignment allowlist. */
    protected $fillable = [
        'name',
        'description',
    ];

    public function item(): HasMany
    {
        return $this->hasMany(G002M007Item::class, 'g002_m003_item_management_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'g002_m003_item_management_user')
            ->withTimestamps();
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(G003M006Room::class, 'g002_m003_item_management_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(G008M017Vehicle::class, 'g002_m003_item_management_id');
    }
}
