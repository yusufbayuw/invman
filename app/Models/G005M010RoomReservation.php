<?php

namespace App\Models;

use App\Models\Concerns\HasLoanReturnControls;
use App\Observers\G005M010RoomReservationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(G005M010RoomReservationObserver::class)]
class G005M010RoomReservation extends Model
{
    protected $guarded = ['id'];

    use HasLoanReturnControls, HasUuids;

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'returned_at' => 'datetime',
        'decision_at' => 'datetime',
        'status_changed_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    public function room_review(): HasMany
    {
        return $this->hasMany(G006M012RoomReview::class, 'g005_m010_room_reservation_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(G003M006Room::class, 'g003_m006_room_id');
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
            ->where('reservation_type', 'room');
    }

    public function loanReservationType(): string
    {
        return 'room';
    }
}
