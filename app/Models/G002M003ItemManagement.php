<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class G002M003ItemManagement extends Model
{
    public function item(): HasMany
    {
        return $this->hasMany(G002M007Item::class, 'g002_m003_item_management_id');
    }
}
