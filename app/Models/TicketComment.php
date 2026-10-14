<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketComment extends Model
{
    use HasUuids;
    protected $fillable = ['ticket_id', 'user_id', 'body', 'is_internal'];
    protected $casts = ['is_internal' => 'boolean'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function attachments(): HasMany { return $this->hasMany(TicketAttachment::class, 'ticket_comment_id'); }
}
