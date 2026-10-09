<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleAssignmentHistory extends Model
{
    protected $guarded = [];
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(G005M019VehicleReservation::class, 'vehicle_reservation_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function oldDriver(): BelongsTo
    {
        return $this->belongsTo(G008M018Driver::class, 'old_driver_id');
    }

    public function newDriver(): BelongsTo
    {
        return $this->belongsTo(G008M018Driver::class, 'new_driver_id');
    }

    public function oldAssistant(): BelongsTo
    {
        return $this->belongsTo(VehicleAssistant::class, 'old_assistant_id');
    }

    public function newAssistant(): BelongsTo
    {
        return $this->belongsTo(VehicleAssistant::class, 'new_assistant_id');
    }
}
