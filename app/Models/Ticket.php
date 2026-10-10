<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Ticket extends Model
{
    use HasUuids;

    protected $fillable = [
        'number', 'ticket_category_id', 'reporter_id', 'g001_m001_unit_id',
        'g002_m003_item_management_id', 'assigned_to', 'g004_m008_activity_id',
        'source_key', 'title', 'description', 'priority', 'status', 'resolved_at', 'closed_at',
    ];

    protected $casts = ['resolved_at' => 'datetime', 'closed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw ValidationException::withMessages([
            'ticket' => 'Tiket dan auditnya tidak dapat dihapus. Gunakan status Dibatalkan.',
        ]));
    }

    public function category(): BelongsTo { return $this->belongsTo(TicketCategory::class, 'ticket_category_id'); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(G001M001Unit::class, 'g001_m001_unit_id'); }
    public function management(): BelongsTo { return $this->belongsTo(G002M003ItemManagement::class, 'g002_m003_item_management_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function activity(): BelongsTo { return $this->belongsTo(G004M008Activity::class, 'g004_m008_activity_id'); }
    public function assets(): HasMany { return $this->hasMany(TicketAssetLink::class); }
    public function comments(): HasMany { return $this->hasMany(TicketComment::class); }
    public function attachments(): HasMany { return $this->hasMany(TicketAttachment::class); }
    public function events(): HasMany { return $this->hasMany(TicketEvent::class); }
}
