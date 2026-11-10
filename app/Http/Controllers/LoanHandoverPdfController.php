<?php

namespace App\Http\Controllers;

use App\Services\LoanHandoverDocumentService;
use App\Services\LoanHandoverQrService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LoanHandoverPdfController extends Controller
{
    public function __invoke(
        Request $request,
        string $type,
        string $reservation,
        LoanHandoverQrService $qr,
        LoanHandoverDocumentService $documents,
    ): Response {
        $record = $qr->resolve($type, $reservation);

        // QR/PDF URLs carry no implicit authority. Validate every download
        // against the live unit/asset-management permissions.
        abort_unless($qr->canView($request->user(), $record), 404);

        $data = $documents->document($type, $record);
        $pdf = Pdf::loadView('pdf.loans.handover-evidence', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false);

        $filename = "bukti-serah-terima-{$type}-{$reservation}.pdf";

        // Keep existing download URLs working; preview uses identical access checks.
        return ($request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
