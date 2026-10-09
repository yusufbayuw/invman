<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleAssistant extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];

    public function reservations(): HasMany
    {
        return $this->hasMany(G005M019VehicleReservation::class, 'vehicle_assistant_id');
    }
}
