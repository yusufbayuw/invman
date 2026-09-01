<?php

namespace App\Models;

use App\Models\Concerns\HasLoanReturnControls;
use App\Observers\G005M019VehicleReservationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(G005M019VehicleReservationObserver::class)]
class G005M019VehicleReservation extends Model
{
    use HasLoanReturnControls, HasUuids;

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'returned_at' => 'datetime',
        'decision_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(G008M017Vehicle::class, 'g008_m017_vehicle_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(G008M018Driver::class, 'g008_m018_driver_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id');
    }

    public function decisionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_by');
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LoanReservationStatusHistory::class, 'reservation_id')
            ->where('reservation_type', 'vehicle');
    }

    public function loanReservationType(): string
    {
        return 'vehicle';
    }
}
