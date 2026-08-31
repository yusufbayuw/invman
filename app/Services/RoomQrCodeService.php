<?php

namespace App\Services;

use App\Models\G003M006Room;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Font\OpenSans;
use Endroid\QrCode\Label\LabelAlignment;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RoomQrCodeService
{
    public function ensure(G003M006Room $room, bool $force = false): string
    {
        if (! $room->qr_uuid) {
            $room->forceFill(['qr_uuid' => (string) Str::uuid()])->saveQuietly();
        }

        $path = "room-qrcodes/room-{$room->qr_uuid}.png";
        $disk = Storage::disk('public');

        if (! $force && $room->qrcode === $path && $disk->exists($path)) {
            return $path;
        }

        $result = (new Builder(
            writer: new PngWriter,
            writerOptions: [],
            validateResult: false,
            data: $room->publicScheduleUrl(),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 500,
            margin: 24,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            logoPath: public_path('images/app/fav.png'),
            logoResizeToWidth: 90,
            logoPunchoutBackground: true,
            labelText: (string) $room->name,
            labelFont: new OpenSans(24),
            labelAlignment: LabelAlignment::Center,
        ))->build();

        $disk->put($path, $result->getString());

        if ($room->qrcode !== $path) {
            $room->forceFill(['qrcode' => $path])->saveQuietly();
        }

        return $path;
    }
}
