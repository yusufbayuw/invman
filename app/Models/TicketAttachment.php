<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    // Native Filament ImageEntry/RepeatableEntry use these URL attributes.
    // URLs are always authorized by TicketAttachmentController.
    protected $appends = ['image_url', 'preview_url', 'download_url'];

    public function getImageUrlAttribute(): ?string
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png'], true)
            ? route('tickets.attachment', ['attachment' => $this->id])
            : null;
    }

    public function getPreviewUrlAttribute(): string
    {
        return route('tickets.attachment', ['attachment' => $this->id]);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('tickets.attachment', ['attachment' => $this->id, 'download' => 1]);
    }

    use HasUuids;
    protected $fillable = ['ticket_id', 'ticket_comment_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function comment(): BelongsTo { return $this->belongsTo(TicketComment::class, 'ticket_comment_id'); }
}
