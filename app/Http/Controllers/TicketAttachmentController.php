<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use App\Services\TicketVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TicketAttachmentController extends Controller
{
    public function __invoke(Request $request, TicketAttachment $attachment, TicketVisibility $visibility): Response
    {
        $ticket = $attachment->ticket;

        abort_unless($ticket && $visibility->canView($request->user(), $ticket), 404);

        // Internal attachments are restricted to ticket managers.
        if ($attachment->ticket_comment_id) {
            $comment = $ticket->comments()->find($attachment->ticket_comment_id);
            abort_if($comment?->is_internal && ! $visibility->canManage($request->user(), $ticket), 404);
        }

        abort_unless($attachment->disk === 'local'
            && str_starts_with($attachment->path, 'tickets/attachments/')
            && ! str_contains($attachment->path, '..')
            && Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
