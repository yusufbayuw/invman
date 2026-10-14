<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use App\Services\TicketAttachmentSecurity;
use App\Services\TicketVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class TicketAttachmentController extends Controller
{
    public function __invoke(
        Request $request,
        TicketAttachment $attachment,
        TicketVisibility $visibility,
        TicketAttachmentSecurity $security,
    ): Response {
        $ticket = $attachment->ticket;

        abort_unless($ticket && $visibility->canView($request->user(), $ticket), 404);

        // The attachment and its parent comment must belong to the same ticket.
        if ($attachment->ticket_comment_id) {
            $comment = $attachment->comment;
            abort_unless($comment && $comment->ticket_id === $ticket->id, 404);
            abort_if($comment->is_internal && ! $visibility->canManage($request->user(), $ticket), 404);
        }

        abort_unless($attachment->disk === 'local', 404);

        try {
            // Inspect already-stored files on every preview, including old uploads.
            // Malware scan is performed on new uploads, not on every image request.
            $file = $security->inspect($attachment->path, scan: false);
        } catch (ValidationException) {
            abort(404);
        }

        $isDownload = $request->boolean('download');
        $disk = Storage::disk('local');
        $safeName = trim(str_replace(["\r", "\n"], '', $attachment->original_name)) ?: 'lampiran';
        $headers = [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Type' => $file['mime'],
            'Content-Security-Policy' => "sandbox",
        ];

        // File previews and downloads remain separately visible in ticket audit.
        $ticket->events()->create([
            'actor_id' => $request->user()->id,
            'action' => $isDownload ? 'attachment_downloaded' : 'attachment_viewed',
            'notes' => $attachment->id,
        ]);

        if ($isDownload) {
            $response = response()->download($disk->path($attachment->path), $safeName, $headers);
        } else {
            $response = response()->file($disk->path($attachment->path), $headers);
            $response->headers->set(
                'Content-Disposition',
                $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_INLINE,
                    $safeName,
                    'file',
                ),
            );
        }

        // Symfony BinaryFileResponse can replace explicitly supplied caching
        // headers with "public"; set them after response construction.
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
