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
.receipt-summary { page-break-inside: avoid; }
.audit-row { page-break-inside: avoid; }
.note { border: 1px solid #b7bdc9; padding: 8px; margin-top: 12px; font-size: 8px; }
.status { text-align:center; font-weight:bold; font-size:10px; padding:7px; border:1px solid #adb8c9; }
</style>
</head>
<body>
@php
    $hierarchyAudit = $ticket->operationalOverrides;
    $acceptOverride = $hierarchyAudit->where("action", "accept_task")->last();
    $finishOverride = $hierarchyAudit->where("action", "finish_work")->last();
    $overrideLabels = ['open_on_behalf' => 'Pelaporan atas nama pegawai', 'work_order_decision' => 'Keputusan persetujuan', 'work_order_estimate' => 'Pencatatan estimasi', 'approve_assign' => 'Penugasan petugas', 'accept_task' => 'Penerimaan tugas', 'finish_work' => 'Pencatatan hasil', 'verify_work' => 'Verifikasi hasil', 'close_work' => 'Pengesahan akhir'];
@endphp
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
<tr><th>Diajukan oleh</th><th>Disetujui oleh</th><th>Penerimaan tugas</th></tr>
<tr>
<td>{{ $ticket->reporter?->name ?? '-' }}<br><small>{{ $ticket->created_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td>{{ $ticket->maintenance->approver?->name ?? 'Belum' }}<br><small>{{ $ticket->maintenance->approved_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td>Petugas ditugaskan: {{ $ticket->assignee?->name ?? 'Belum' }}
@if($ticket->maintenance->accepted_at)
<br>Penerimaan dicatat oleh: <strong>{{ $acceptOverride?->actor?->name ?? $ticket->maintenance->technician?->name ?? '-' }}</strong>
@if($acceptOverride)<small>(override)</small>@endif
@endif
<br><small>{{ $ticket->maintenance->accepted_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
</tr>
</table>
<p><b>Catatan persetujuan:</b> {{ $ticket->maintenance->approval_notes ?? '-' }}</p>
<p><b>Deadline respons:</b> {{ $ticket->maintenance->response_due_at?->format('d/m/Y H:i') ?? '-' }} &nbsp;
<b>Deadline penyelesaian:</b> {{ $ticket->maintenance->resolution_due_at?->format('d/m/Y H:i') ?? '-' }}</p>

@if($ticket->workOrderApprovals->isNotEmpty())
<p><b>Jejak persetujuan Work Order</b> · Konfigurasi versi {{ $ticket->workOrderApprovals->first()->workflow?->version ?? '-' }}</p>
<table class="bordered">
<tr><th>Tahap</th><th>Status</th><th>Keputusan oleh / waktu</th><th>Catatan</th></tr>
@foreach($ticket->workOrderApprovalHistory->sortBy([['revision', 'asc'], ['sequence', 'asc']]) as $approval)
<tr>
<td>{{ $approval->label }}<br><small>Revisi {{ $approval->revision }} · {{ $approval->superseded_at ? 'Arsip' : 'Aktif' }} · {{ $approval->checkpoint === 'final' ? 'Pengesahan akhir' : 'Sebelum penugasan' }}</small></td>
<td>{{ ['approved' => 'Disetujui', 'rejected' => 'Ditolak', 'pending' => 'Menunggu', 'blocked' => 'Belum ada pejabat'] [$approval->status] ?? $approval->status }}</td>
<td>{{ $approval->decisionMaker?->name ?? 'Belum diputuskan' }}<br><small>{{ $approval->decided_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td class="long">{{ $approval->notes ?? '-' }}@if($approval->estimate_id)<br>Estimasi #{{ $approval->estimate_id }}@endif</td>
</tr>
@endforeach
</table>
@foreach($ticket->workOrderRevisions as $revision)
<p><b>Pengiriman ulang revisi {{ $revision->revision }}</b> · {{ $revision->actor?->name ?? '-' }} · {{ $revision->created_at?->format('d/m/Y H:i') }}<br>{{ $revision->reason }}</p>
@endforeach
@endif

<h2>IV. TINDAKAN PERBAIKAN (DIISI OLEH TEKNISI / MAINTENANCE)</h2>
@foreach($ticket->workOrderEstimates as $estimate)
<p><b>Tinjauan / estimasi #{{ $estimate->id }}</b> · Versi {{ $estimate->version }} · Revisi laporan {{ $estimate->revision }}<br>
Pelaksana: {{ $estimate->execution_mode === 'external' ? 'Vendor eksternal' : 'Tim internal' }} · {{ $estimate->vendor_name ?? '-' }}<br>
Diagnosis awal: {{ $estimate->diagnosis }}<br>
Total estimasi: Rp {{ number_format((int) $estimate->amount, 0, ',', '.') }}</p>
<table class="bordered"><tr><th>Rencana material</th><th>Jumlah</th><th>Satuan</th></tr>
@foreach($estimate->materials as $part)<tr><td>{{ $part['name'] }}</td><td>{{ $part['quantity'] }}</td><td>{{ $part['unit'] }}</td></tr>@endforeach
</table>
@endforeach
<table>
<tr><td class="key">Teknisi penanggung jawab</td><td>{{ $ticket->assignee?->name ?? '-' }}</td></tr>
<tr><td class="key">Tanggal mulai</td><td>{{ $ticket->maintenance->accepted_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
<tr><td class="key">Tanggal selesai</td><td>{{ $ticket->maintenance->work_finished_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
<tr><td class="key">Analisis masalah</td><td class="long">{{ $ticket->maintenance->diagnosis ?? 'Belum diisi' }}</td></tr>
<tr><td class="key">Tindakan perbaikan</td><td class="long">{{ $ticket->maintenance->repair_actions ?? 'Belum diisi' }}</td></tr>
</table>
@if($ticket->maintenance->work_finished_at)
<p>Hasil dicatat oleh: <strong>{{ $finishOverride?->actor?->name ?? $ticket->maintenance->technician?->name ?? '-' }}</strong>@if($finishOverride) <small>(override atas nama petugas)</small>@endif</p>
@endif
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

<div class="receipt-summary">
<h2>V. SERAH TERIMA & PENYELESAIAN</h2>
<table class="bordered">
<tr><th>Teknisi penanggung jawab</th><th>Pelapor (verifikasi)</th><th>Manajer (mengetahui)</th></tr>
<tr>
<td>{{ $ticket->assignee?->name ?? '-' }}<br><small>{{ $ticket->maintenance->work_finished_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td>{{ $ticket->maintenance->verifier?->name ?? 'Belum diverifikasi' }}<br><small>{{ $ticket->maintenance->verified_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
<td>{{ $ticket->maintenance->finalApprover?->name ?? 'Belum disahkan' }}<br><small>{{ $ticket->maintenance->final_approved_at?->format('d/m/Y H:i') ?? '-' }}</small></td>
</tr>
</table>
<p><b>Kondisi kelayakan:</b> {{ $ticket->maintenance->asset_released_at ? 'Dinyatakan layak pakai setelah pemeriksaan' : 'Belum dinyatakan layak pakai / penahanan aset tetap berlaku' }}</p>
<p><b>Catatan pengesahan:</b> {{ $ticket->maintenance->closure_notes ?? '-' }}</p>
</div>
@if($hierarchyAudit->isNotEmpty())
<h2>VI. AUDIT PENGECUALIAN HIERARKI</h2>
<p>Identitas petugas dan pelapor asli dipertahankan. Tindakan berikut dicatat oleh akun pelaku yang benar-benar login. Waktu audit ditampilkan sesuai rekaman tersimpan; zona waktu audit lama tidak tercatat.</p>
<table class="bordered">
<thead><tr><th>Tindakan</th><th>Pelaku login</th><th>Pegawai diwakili</th><th>Waktu tersimpan / alasan</th></tr></thead>
<tbody>
@foreach($hierarchyAudit as $override)
<tr class="audit-row">
<td>{{ $overrideLabels[$override->action] ?? $override->action }}<br><small>{{ $override->from_status ?? '-' }} → {{ $override->to_status ?? '-' }}</small></td>
<td>{{ $override->actor?->name ?? '-' }}@if($override->details['self_approval'] ?? false)<br><b>Persetujuan diri sendiri (override)</b>@endif</td>
<td>{{ $override->subject?->name ?? 'Kewenangan tahap' }}</td>
<td class="long">{{ $override->created_at?->format('d/m/Y H:i') }}<br><small>{{ $override->details['recorded_timezone'] ?? 'Zona waktu historis tidak terekam' }}</small><br>{{ $override->reason }}</td>
</tr>
@endforeach
</tbody>
</table>
@endif
<div class="note">
    Ini adalah catatan persetujuan elektronik internal berdasarkan identitas login dan waktu server,
    bukan tanda tangan digital tersertifikasi. Kolom yang belum diproses tidak direkayasa menjadi persetujuan.
    Detail histori dan lampiran bukti tersimpan privat di InvMan; perubahan yang sah setelah cetak
    akan tercermin pada versi PDF berikutnya.
</div>
</body>
</html>
