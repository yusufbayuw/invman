<?php

namespace App\Models;

use App\Models\Concerns\HasLoanReturnControls;
use App\Observers\G005M009ItemReservationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(G005M009ItemReservationObserver::class)]
class G005M009ItemReservation extends Model
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

    public function item_reservation_detail(): HasMany
    {
        return $this->hasMany(G005M016ItemReservationDetail::class, 'g005_m009_item_reservation_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(G002M007Item::class, 'g002_m007_item_id');
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
            ->where('reservation_type', 'item');
    }

    public function loanReservationType(): string
    {
        return 'item';
    }
}
