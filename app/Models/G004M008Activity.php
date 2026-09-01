<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class G004M008Activity extends Model
{
    use HasUuids;

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'hold_expires_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $status = $activity->status ?: \App\Enums\ReservationStatus::Submitted->value;

            if ($status === \App\Enums\ReservationStatus::Submitted->value && ! $activity->hold_expires_at) {
                $deadline = now()->addHours(app(\App\Services\LoanSettings::class)->holdHours());
                $activity->hold_expires_at = $activity->start_time && $activity->start_time->lessThan($deadline)
                    ? $activity->start_time
                    : $deadline;
            }
        });

        static::deleting(function (self $activity): void {
            if ($activity->status !== \App\Enums\ReservationStatus::Draft->value) {
                throw ValidationException::withMessages([
                    'activity' => 'Pengajuan yang sudah dikirim tidak boleh dihapus. Gunakan pembatalan agar histori tetap utuh.',
                ]);
            }
        });
    }

    public function item_reservation(): HasMany
    {
        return $this->hasMany(G005M009ItemReservation::class, 'g004_m008_activity_id');
    }

    public function room_reservation(): HasMany
    {
        return $this->hasMany(G005M010RoomReservation::class, 'g004_m008_activity_id');
    }

    public function vehicle_reservation(): HasMany
    {
        return $this->hasMany(G005M019VehicleReservation::class, 'g004_m008_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }

    public function return_checklist(): HasOne
    {
        return $this->hasOne(LoanRequestChecklist::class, 'g004_m008_activity_id')
            ->where('stage', 'return');
    }

    public function review(): HasOne
    {
        return $this->hasOne(LoanRequestReview::class, 'g004_m008_activity_id');
    }

    public function item_reviews(): HasMany
    {
        return $this->hasMany(G006M011ItemReview::class, 'g004_m008_activity_id');
    }

    public function room_reviews(): HasMany
    {
        return $this->hasMany(G006M012RoomReview::class, 'g004_m008_activity_id');
    }

    public function vehicle_reviews(): HasMany
    {
        return $this->hasMany(G006M020VehicleReview::class, 'g004_m008_activity_id');
    }
}
