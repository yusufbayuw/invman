<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>QR Code {{ $room->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; text-align: center; }
        .sheet { height: 235mm; border: 1.5px solid #d9deea; border-radius: 8mm; padding: 16mm 14mm 10mm; }
        .kicker { margin: 0 0 4mm; color: #4f46e5; font-size: 10pt; font-weight: bold; letter-spacing: 1.8px; text-transform: uppercase; }
        h1 { margin: 0; font-size: 25pt; line-height: 1.2; }
        .location { height: 8mm; margin: 3mm 0 7mm; color: #667085; font-size: 10pt; }
        .qr { display: block; width: 138mm; height: auto; margin: 0 auto; }
        .instruction { margin: 8mm 0 2mm; font-size: 13pt; font-weight: bold; }
        .url { margin: 0; color: #667085; font-size: 8.5pt; }
        .footer { margin-top: 8mm; padding-top: 5mm; border-top: 1px solid #e5e7eb; color: #667085; font-size: 8pt; }
    </style>
</head>
<body>
    <main class="sheet">
        <p class="kicker">Informasi Jadwal Ruangan</p>
        <h1>{{ $room->name }}</h1>
        <p class="location">
            {{ collect([$room->floor?->building?->name, $room->floor?->name])->filter()->implode(' - ') }}
        </p>
        <img class="qr" src="{{ $qrCodeDataUri }}" alt="QR Code {{ $room->name }}">
        <p class="instruction">Pindai untuk melihat jadwal peminjaman</p>
        <p class="url">{{ $room->publicScheduleUrl() }}</p>
        <p class="footer">Ukuran A4 - tempelkan lembar ini di area yang mudah dipindai.</p>
    </main>
</body>
</html>
