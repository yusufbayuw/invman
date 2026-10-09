<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class G008M017Vehicle extends Model
{
    /** Explicit, audited mass-assignment allowlist. */
    protected $fillable = [
        'g001_m001_unit_id',
        'g002_m003_item_management_id',
        'default_driver_id',
        'requires_assistant',
        'name',
        'license_plate',
        'stnk_date',
        'kir_date',
        'capacity',
        'is_borrowable',
        'status',
    ];

    protected $casts = [
        'stnk_date' => 'date',
        'kir_date' => 'date',
        'is_borrowable' => 'boolean',
        'requires_assistant' => 'boolean',
    ];

    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(G008M018Driver::class, 'default_driver_id');
    }

    public function vehicle_reservation(): HasMany
    {
        return $this->hasMany(G005M019VehicleReservation::class, 'g008_m017_vehicle_id');
    }
    public function vehicle_review(): HasMany
    {
        return $this->hasMany(G006M020VehicleReview::class, 'g008_m017_vehicle_id');
    }
    public function vehicle_history(): HasMany
    {
        return $this->hasMany(G007M021VehicleHistory::class, 'g008_m017_vehicle_id');
    }
    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }
    public function item_management(): BelongsTo
    {
        return $this->belongsTo(G002M003ItemManagement::class, 'g002_m003_item_management_id');
    }
}
