<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bukti Serah Terima - {{ $assetName }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm 14mm 17mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #172334; line-height: 1.5; }
        h1, h2, h3, p { margin: 0; }
        h1 { font-size: 17pt; line-height: 1.3; margin: 3mm 0 1mm; }
        h2 { font-size: 11pt; margin: 0 0 3mm; color: #1e3a5f; }
        p { margin: 1.5mm 0; }
        .eyebrow { text-transform: uppercase; color: #4f6282; font-size: 7.5pt; letter-spacing: 1.4px; font-weight: bold; }
        .muted { color: #60738c; }
        .top { padding-bottom: 5mm; border-bottom: 2px solid #1d4ed8; }
        .meta { font-size: 8pt; margin-top: 2mm; color: #516178; }
        .status { padding: 3mm 3.5mm; background: #eff6ff; border: 1px solid #bfd8ff; margin: 4mm 0; }
        .status.warning { background: #fffbeb; border-color: #f6d88e; }
        .status.danger { background: #fff3f3; border-color: #f4b5b5; }
        .status strong { font-size: 10pt; }
        .block { margin-top: 5mm; page-break-inside: avoid; }
        .block.long { page-break-inside: auto; }
        table { border-collapse: collapse; width: 100%; }
        .facts th { width: 29%; text-align: left; vertical-align: top; font-weight: normal; color: #5b687b; padding: 1.25mm 2mm 1.25mm 0; }
        .facts td { vertical-align: top; padding: 1.25mm 0; font-weight: 600; overflow-wrap: break-word; }
        .half { width: 49%; vertical-align: top; }
        .line { border-top: 1px solid #dce4ee; margin: 3mm 0; }
        .panel { border: 1px solid #dce4ee; padding: 3.5mm; margin: 2mm 0; }
        .panel-title { text-transform: uppercase; letter-spacing: .6px; color: #416184; font-weight: bold; font-size: 7.8pt; padding-bottom: 2mm; }
        .pill { display: inline-block; border: 1px solid #bac7d6; padding: .75mm 2mm; font-size: 7.5pt; font-weight: bold; }
        .note { padding: 2.3mm 3mm; background: #f8fafc; color: #40546a; }
        .alert { color: #8b4513; padding: 2mm 3mm; background: #fff7e8; border-left: 3px solid #e9a23b; }
        .data { table-layout: fixed; }
        .data th { text-align: left; color: #51617b; font-size: 8pt; background: #edf2f9; padding: 2mm; border: 1px solid #dce4ee; }
        .data td { vertical-align: top; padding: 2mm; border: 1px solid #dce4ee; word-wrap: break-word; }
        .data tr { page-break-inside: avoid; }
        .small { font-size: 7.5pt; }
        .photo { margin-top: 2mm; width: auto; max-width: 58mm; max-height: 44mm; }
        .evidence { border-top: 1px dashed #dce4ee; margin: 3mm 0; padding-top: 3mm; }
        .footer { position: fixed; bottom: -11mm; left: 0; right: 0; border-top: 1px solid #dce4ee; padding-top: 2mm; font-size: 7pt; color: #5c6c82; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">
        {{ config('app.name', 'InvMan') }} &bull; Dicetak {{ $generatedAt->format('d/m/Y H:i') }} ({{ config('app.timezone', 'UTC') }})
        &bull; Sumber: histori reservasi {{ $reservation->getKey() }}
    </div>

    <header class="top">
        <p class="eyebrow">{{ config('app.name', 'InvMan') }} / Administrasi Aset</p>
        <h1>Bukti Serah-Terima Digital</h1>
        <p class="muted">Dokumen administratif penyerahan awal dan pengembalian aset.</p>
        <p class="meta">Jenis: {{ $typeLabel }} &nbsp; | &nbsp; Reservasi: {{ $reservation->getKey() }}</p>
    </header>

    <div class="status {{ $hasException ? 'danger' : (! $complete ? 'warning' : '') }}">
        @if($hasException)
            <strong>PENYERAHAN KHUSUS / PENGECUALIAN TERCATAT</strong>
            <p>Konfirmasi penerimaan peminjam tidak lengkap. Alasan pengecualian tercantum di bagian bukti penyerahan.</p>
        @elseif(!$complete)
            <strong>DOKUMEN BELUM LENGKAP</strong>
            <p>Setidaknya satu tahap penyerahan/pengembalian belum memiliki konfirmasi lengkap. Jangan dianggap bukti serah-terima selesai.</p>
        @else
            <strong>RIWAYAT SERAH-TERIMA LENGKAP</strong>
            <p>Kedua tahap memiliki pencatatan penyelesaian dari aplikasi.</p>
        @endif
    </div>

    <section class="block">
        <h2>Identitas Kegiatan &amp; Reservasi</h2>
        <table class="facts">
            <tr><th>Kegiatan master</th><td>{{ $reservation->activity?->loanEvent?->name ?? $reservation->activity?->name ?? '-' }}</td></tr>
            <tr><th>Pengajuan / keperluan</th><td>{{ $reservation->activity?->name ?? '-' }}</td></tr>
            <tr><th>Deskripsi</th><td>{{ $reservation->activity?->description ?: '-' }}</td></tr>
            <tr><th>Unit pemohon</th><td>{{ $reservation->activity?->unit?->name ?? '-' }}</td></tr>
            <tr><th>Pemohon</th><td>{{ $reservation->activity?->user?->name ?? '-' }}</td></tr>
            <tr><th>Jenis &amp; nama aset</th><td>{{ $typeLabel }} - {{ $assetName }}</td></tr>
            @if($assetDescription !== '')
                <tr><th>Identitas / penugasan</th><td>{{ $assetDescription }}</td></tr>
            @endif
            <tr><th>Jumlah</th><td>{{ $quantity }}</td></tr>
            <tr><th>Jadwal pinjam</th><td>{{ $reservation->start_time?->format('d/m/Y H:i') ?? '-' }} s.d. {{ $reservation->end_time?->format('d/m/Y H:i') ?? '-' }}</td></tr>
            <tr><th>Status saat dicetak</th><td>{{ $statusLabel }}</td></tr>
        </table>
        @if(count($unitItems))
            <p class="small muted"><strong>Unit barang yang dialokasikan:</strong> {{ implode(', ', $unitItems) }}</p>
        @endif
    </section>

    <section class="block long">
        <h2>1. Penyerahan Awal / Keluar</h2>
        @if(!$checkout)
            <div class="note">Bukti penyerahan awal belum tersedia pada sistem.</div>
        @else
            <div class="panel">
                <div class="panel-title">Bukti {{ $checkout['number'] }}</div>
                <table class="facts">
                    <tr><th>Pengelola</th><td>{{ $checkout['manager'] }} &mdash; {{ $checkout['managerAt']?->format('d/m/Y H:i') ?? 'Belum konfirmasi' }}</td></tr>
                    <tr><th>Penerima / peminjam</th><td>{{ $checkout['borrower'] }} &mdash; {{ $checkout['borrowerAt']?->format('d/m/Y H:i') ?? 'Belum konfirmasi' }}</td></tr>
                    <tr><th>Penyelesaian</th><td>{{ $checkout['completedAt']?->format('d/m/Y H:i') ?? 'Belum selesai' }}</td></tr>
                    @if($checkout['odometer'] !== null)
                        <tr><th>KM awal kendaraan</th><td>{{ number_format($checkout['odometer'], 0, ',', '.') }} km</td></tr>
                    @endif
                    <tr><th>Catatan</th><td>{{ $checkout['notes'] ?: '-' }}</td></tr>
                </table>
                @if($checkout['fallbackReason'])
                    <p class="alert"><strong>Alasan pengecualian:</strong> {{ $checkout['fallbackReason'] }}</p>
                @endif
                @if($checkout['proofImage'])
                    <div class="evidence"><p class="small">Foto bukti penyerahan</p><img class="photo" src="{{ $checkout['proofImage'] }}" alt="Bukti penyerahan"></div>
                @elseif($checkout['hasProof'])
                    <p class="small muted">Lampiran bukti penyerahan tersimpan pada sistem; tidak disisipkan pada PDF.</p>
                @endif
            </div>
        @endif

        <h3 class="panel-title" style="margin-top:3mm">Pemeriksaan kondisi awal</h3>
        @if(empty($checkoutRows))
            <div class="note">Checklist kondisi awal tidak terekam pada transaksi ini. Dokumen tidak mengasumsikan kondisi aset baik.</div>
        @else
            <table class="data">
                <thead><tr><th style="width:24%">Aset / inventaris</th><th style="width:17%">Kondisi</th><th style="width:37%">Catatan</th><th style="width:22%">Pemeriksa</th></tr></thead>
                <tbody>
                    @foreach($checkoutRows as $check)
                        <tr>
                            <td>{{ $check['instance'] }}</td>
                            <td><strong>{{ $check['good'] ? 'Baik' : 'Perlu perhatian' }}</strong></td>
                            <td>
                                {{ $check['notes'] ?: '-' }}
                                @if($check['photo'])<img class="photo" src="{{ $check['photo'] }}" alt="Foto kondisi awal">@elseif($check['hasPhoto'])<p class="small muted">Foto tersimpan di sistem</p>@endif
                            </td>
                            <td>{{ $check['checkedBy'] }}<div class="small muted">{{ $check['checkedAt']?->format('d/m/Y H:i') ?? '-' }}</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="block long">
        <h2>2. Pengembalian Aset</h2>
        @if(!$returnEvidence)
            <div class="note">Bukti pengembalian belum tersedia pada sistem.</div>
        @else
            <div class="panel">
                <div class="panel-title">Bukti {{ $returnEvidence['number'] }}</div>
                <table class="facts">
                    <tr><th>Peminjam</th><td>{{ $returnEvidence['borrower'] }} &mdash; {{ $returnEvidence['borrowerAt']?->format('d/m/Y H:i') ?? 'Belum konfirmasi' }}</td></tr>
                    <tr><th>Pengelola</th><td>{{ $returnEvidence['manager'] }} &mdash; {{ $returnEvidence['managerAt']?->format('d/m/Y H:i') ?? 'Belum konfirmasi' }}</td></tr>
                    <tr><th>Penyelesaian</th><td>{{ $returnEvidence['completedAt']?->format('d/m/Y H:i') ?? 'Belum selesai' }}</td></tr>
                    <tr><th>Catatan</th><td>{{ $returnEvidence['notes'] ?: '-' }}</td></tr>
                </table>
                @if($returnEvidence['proofImage'])
                    <div class="evidence"><p class="small">Foto bukti pengembalian</p><img class="photo" src="{{ $returnEvidence['proofImage'] }}" alt="Bukti pengembalian"></div>
                @elseif($returnEvidence['hasProof'])
                    <p class="small muted">Lampiran pengembalian tersimpan pada sistem; tidak disisipkan pada PDF.</p>
                @endif
            </div>
        @endif

        <h3 class="panel-title" style="margin-top:3mm">Pemeriksaan kondisi akhir</h3>
        @if(empty($returnRows))
            <div class="note">Checklist pengembalian belum terekam.</div>
        @else
            <table class="data">
                <thead><tr><th style="width:24%">Aset / inventaris</th><th style="width:17%">Kondisi</th><th style="width:37%">Catatan</th><th style="width:22%">Pemeriksa</th></tr></thead>
                <tbody>
                    @foreach($returnRows as $check)
                        <tr>
                            <td>{{ $check['instance'] }}</td>
                            <td><strong>{{ $check['good'] ? 'Baik' : 'Perlu perhatian' }}</strong></td>
                            <td>
                                {{ $check['notes'] ?: '-' }}
                                @if($check['photo'])<img class="photo" src="{{ $check['photo'] }}" alt="Foto kondisi akhir">@elseif($check['hasPhoto'])<p class="small muted">Foto tersimpan di sistem</p>@endif
                            </td>
                            <td>{{ $check['checkedBy'] }}<div class="small muted">{{ $check['checkedAt']?->format('d/m/Y H:i') ?? '-' }}</div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="block">
        <div class="line"></div>
        <p class="small muted">Bukti ini dihasilkan otomatis dari pencatatan InvMan saat diunduh. Nama dan waktu adalah catatan konfirmasi akun pada aplikasi, <strong>bukan tanda tangan elektronik tersertifikasi</strong>. Dokumen dapat berubah apabila histori transaksinya diperbarui secara sah. Periksa nomor reservasi dan bukti asli di aplikasi untuk validasi.</p>
    </section>
</body>
</html>
