<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    use HasUuids;
    protected $fillable = ['ticket_id', 'ticket_comment_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function comment(): BelongsTo { return $this->belongsTo(TicketComment::class, 'ticket_comment_id'); }
}
