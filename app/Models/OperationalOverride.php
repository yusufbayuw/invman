<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableAuditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalOverride extends Model
{
    use HasImmutableAuditRecord;

    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'subject_user_id', 'entity_type', 'entity_id', 'action',
        'from_status', 'to_status', 'reason', 'details', 'ip_address', 'user_agent',
    ];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            // Database CURRENT_TIMESTAMP may use another timezone. Capture the
            // application clock and its zone together for every new audit entry.
            $zone = (string) config('app.timezone', 'UTC');
            $entry->created_at ??= now($zone);
            $details = $entry->details ?? [];
            $details['recorded_timezone'] = $zone;
            $entry->details = $details;
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }
}
