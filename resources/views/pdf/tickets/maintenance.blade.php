<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Formulir Perawatan {{ $ticket->number }}</title>
<style>
@page { margin: 24px 30px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #121722; line-height: 1.4; }
h1 { text-align:center; font-size: 13px; margin-bottom: 2px; }
small { color: #667085; }
.subtitle { text-align: center; margin: 0 0 14px; }
h2 { color: #19416e; font-size: 10px; padding: 6px 0 3px; border-bottom: 1px solid #b6c4d5; }
table { width: 100%; border-collapse: collapse; }
td,th { text-align:left; vertical-align:top; padding: 3px 5px; }
th { background: #eef2f8; }
.bordered td,.bordered th { border:1px solid #c7cbd1; }
.key { width: 150px; color: #475467; }
.long { white-space: pre-wrap; word-wrap: break-word; }
.page-break { page-break-before: always; }
.note { border: 1px solid #b7bdc9; padding: 8px; margin-top: 12px; font-size: 8px; }
.status { text-align:center; font-weight:bold; font-size:10px; padding:7px; border:1px solid #adb8c9; }
</style>
</head>
<body>
<h1>FORMULIR PERMINTAAN PERAWATAN / PERBAIKAN</h1>
<div class="subtitle">
    Dokumen: {{ $ticket->number }} · Dicetak {{ now()->format('d/m/Y H:i') }}<br>
    <small>Rekaman digital InvMan · Persetujuan dan identitas bersumber dari akun login</small>
</div>

@if(!$ticket->maintenance->final_approved_at)
<div class="status">DOKUMEN DALAM PROSES — BELUM DISETUJUI SEPENUHNYA</div>
@endif

<h2>I. DATA PEMOHON (DIISI OLEH PELAPOR)</h2>
<table>
<tr><td class="key">Nama pelapor</td><td>{{ $ticket->reporter?->name ?? 'Tidak tercatat' }}</td></tr>
<tr><td class="key">Unit kerja</td><td>{{ $ticket->unit?->name ?? '-' }}</td></tr>
<tr><td class="key">Tanggal / jam laporan</td><td>{{ $ticket->created_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
<tr><td class="key">Lokasi kerusakan</td><td>{{ $ticket->location ?? '-' }}</td></tr>
</table>
<h2>II. DESKRIPSI KERUSAKAN</h2>
<table>
<tr><td class="key">Jenis masalah</td><td>{{ $ticket->category?->name ?? '-' }}</td></tr>
<tr><td class="key">Urgensi pelapor</td><td>{{ $ticket->reported_urgency ?? 'Tidak tercatat' }}</td></tr>
<tr><td class="key">Prioritas operasional</td><td>{{ $ticket->priority }}</td></tr>
<tr><td class="key">Uraian kerusakan</td><td class="long">{{ $ticket->description }}</td></tr>
<tr><td class="key">Inventaris terkait</td><td>
@forelse($ticket->assets as $asset)
{{ $asset->asset_name }} ({{ $asset->asset_type }} #{{ $asset->asset_id }})@if(!$loop->last), @endif
@empty Tidak diketahui / belum ditautkan
@endforelse
</td></tr>
</table>
<h2>III. VALIDASI & PERSETUJUAN</h2>
<table class="bordered">
<tr><th>Diajukan oleh</th><th>Disetujui oleh</th><th>Diterima teknisi</th></tr>
<tr>
<td>{{ $ticket->reporter?->name ?? '-' }}<br><small>{{ $ticket->created_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td>{{ $ticket->maintenance->approver?->name ?? 'Belum' }}<br><small>{{ $ticket->maintenance->approved_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td>{{ $ticket->maintenance->technician?->name ?? 'Belum' }}<br><small>{{ $ticket->maintenance->accepted_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
</tr>
</table>
<p><b>Catatan persetujuan:</b> {{ $ticket->maintenance->approval_notes ?? '-' }}</p>
<p><b>Deadline respons:</b> {{ $ticket->maintenance->response_due_at?->format('d/m/Y H:i') ?? '-' }} &nbsp;
<b>Deadline penyelesaian:</b> {{ $ticket->maintenance->resolution_due_at?->format('d/m/Y H:i') ?? '-' }}</p>

<h2>IV. TINDAKAN PERBAIKAN (DIISI OLEH TEKNISI / MAINTENANCE)</h2>
<table>
<tr><td class="key">Nama teknisi</td><td>{{ $ticket->maintenance->technician?->name ?? '-' }}</td></tr>
<tr><td class="key">Tanggal mulai</td><td>{{ $ticket->maintenance->accepted_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
<tr><td class="key">Tanggal selesai</td><td>{{ $ticket->maintenance->work_finished_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
<tr><td class="key">Analisis masalah</td><td class="long">{{ $ticket->maintenance->diagnosis ?? 'Belum diisi' }}</td></tr>
<tr><td class="key">Tindakan perbaikan</td><td class="long">{{ $ticket->maintenance->repair_actions ?? 'Belum diisi' }}</td></tr>
</table>
<p><b>Material / Sparepart:</b></p>
<table class="bordered">
<tr><th>Nama material</th><th>Qty</th><th>Satuan</th></tr>
@forelse($ticket->maintenance->materials ?? [] as $part)
<tr><td>{{ $part['name'] ?? '-' }}</td><td>{{ $part['quantity'] ?? '-' }}</td><td>{{ $part['unit'] ?? '-' }}</td></tr>
@empty
<tr><td colspan="3">Tidak ada material tercatat.</td></tr>
@endforelse
</table>
<p><b>Status akhir:</b>
@switch($ticket->maintenance->outcome)
@case('complete') Selesai sempurna @break
@case('temporary') Selesai sementara @break
@case('unrepairable') Tidak dapat diperbaiki @break
@default Belum ada hasil
@endswitch
</p>

<h2>V. SERAH TERIMA & PENYELESAIAN</h2>
<table class="bordered">
<tr><th>Teknisi yang mengerjakan</th><th>Pelapor (verifikasi)</th><th>Manajer (mengetahui)</th></tr>
<tr>
<td>{{ $ticket->maintenance->technician?->name ?? '-' }}<br><small>{{ $ticket->maintenance->work_finished_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td>{{ $ticket->maintenance->verifier?->name ?? 'Belum diverifikasi' }}<br><small>{{ $ticket->maintenance->verified_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td>{{ $ticket->maintenance->finalApprover?->name ?? 'Belum disahkan' }}<br><small>{{ $ticket->maintenance->final_approved_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
</tr>
</table>
<p><b>Kondisi kelayakan:</b> {{ $ticket->maintenance->asset_released_at ? 'Dinyatakan layak pakai setelah pemeriksaan' : 'Belum dinyatakan layak pakai / penahanan aset tetap berlaku' }}</p>
<p><b>Catatan pengesahan:</b> {{ $ticket->maintenance->closure_notes ?? '-' }}</p>
<div class="note">
    Ini adalah catatan persetujuan elektronik internal berdasarkan identitas login dan waktu server,
    bukan tanda tangan digital tersertifikasi. Kolom yang belum diproses tidak direkayasa menjadi persetujuan.
    Detail histori dan lampiran bukti tersimpan privat di InvMan; perubahan yang sah setelah cetak
    akan tercermin pada versi PDF berikutnya.
</div>
</body>
</html>
