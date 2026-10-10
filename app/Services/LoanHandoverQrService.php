<?php

namespace App\Services;

use App\Models\G005M009ItemReservation;
use App\Models\G005M010RoomReservation;
use App\Models\G005M019VehicleReservation;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class LoanHandoverQrService
{
    public function resolve(string $type, string $id): ?Model
    {
        $class = match ($type) {
            'item' => G005M009ItemReservation::class,
            'room' => G005M010RoomReservation::class,
            'vehicle' => G005M019VehicleReservation::class,
            default => null,
        };

        return $class ? $class::query()->with(['activity', 'returnReceipt'])->find($id) : null;
    }

    public function canView(?User $user, ?Model $reservation): bool
    {
        return $user && $reservation
            && ($user->belongsToUnit($reservation->activity?->g001_m001_unit_id)
                || $user->managesReservation($reservation));
    }

    public function signedScanUrl(string $type, Model $reservation): string
    {
        // A QR is an ephemeral deep link, never an authorization token.
        return URL::temporarySignedRoute('loans.handover.scan', now()->addMinutes(30), [
            'type' => $type,
            'reservation' => (string) $reservation->getKey(),
        ]);
    }

    public function pngDataUri(string $type, Model $reservation): string
    {
        return (new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $this->signedScanUrl($type, $reservation),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 280,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        ))->build()->getDataUri();
    }

    public function assetName(string $type, Model $reservation): string
    {
        return match ($type) {
            'item' => $reservation->item?->name ?? 'Barang',
            'room' => $reservation->room?->name ?? 'Ruangan',
            'vehicle' => $reservation->vehicle?->name ?? 'Kendaraan',
            default => '-',
        };
    }
}
