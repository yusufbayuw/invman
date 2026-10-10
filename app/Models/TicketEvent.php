<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class TicketEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['ticket_id', 'actor_id', 'action', 'from_value', 'to_value', 'notes'];
    protected $casts = ['created_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn (): never => throw ValidationException::withMessages(['audit' => 'Audit tiket tidak dapat diubah.']));
        static::deleting(fn (): never => throw ValidationException::withMessages(['audit' => 'Audit tiket tidak dapat dihapus.']));
    }
    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
