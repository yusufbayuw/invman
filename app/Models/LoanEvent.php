<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class LoanEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'g001_m001_unit_id', 'created_by', 'name', 'description', 'start_time', 'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    protected static function booted(): void
    {
        // A used master event is historical context, not a disposable request.
        static::deleting(function (self $event): void {
            if ($event->requests()->exists()) {
                throw ValidationException::withMessages([
                    'event' => 'Kegiatan dengan pengajuan terkait tidak boleh dihapus.',
                ]);
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(G004M008Activity::class, 'loan_event_id');
    }
}
