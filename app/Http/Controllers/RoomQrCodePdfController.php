<?php

namespace App\Http\Controllers;

use App\Models\G003M006Room;
use App\Services\RoomQrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoomQrCodePdfController extends Controller
{
    public function __invoke(Request $request, G003M006Room $room, RoomQrCodeService $qrCodes): Response
    {
        $room->loadMissing('floor.building');
        $qrCodePath = $qrCodes->ensure($room);
        $qrCodeDataUri = 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($qrCodePath));

        $pdf = Pdf::loadView('pdf.rooms.qrcode-a4', compact('room', 'qrCodeDataUri'))
            ->setPaper('a4', 'portrait');

        $filename = Str::slug((string) $room->name).'-qrcode-a4.pdf';

        return ($request->boolean('preview') ? $pdf->stream($filename) : $pdf->download($filename))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
