<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketVisibility;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketMaintenancePdfController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket, TicketVisibility $access): Response
    {
        abort_unless($access->canView($request->user(), $ticket) && $ticket->maintenance()->exists(), 404);
        $ticket->load(['maintenance.approver', 'maintenance.technician', 'maintenance.verifier',
            'maintenance.finalApprover', 'reporter', 'unit', 'category', 'assets', 'assignee']);

        return Pdf::loadView('pdf.tickets.maintenance', ['ticket' => $ticket])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->download('perawatan-'.$ticket->number.'.pdf')
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
