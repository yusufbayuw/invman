<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $room->name }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('images/app/fav.png') }}">
    <style>
        :root { color-scheme: light; --ink:#172033; --muted:#667085; --line:#e5e7eb; --brand:#4f46e5; --ok:#047857; --ok-bg:#ecfdf5; --busy:#1d4ed8; --busy-bg:#eff6ff; --off:#b42318; --off-bg:#fef3f2; }
        * { box-sizing:border-box; }
        body { margin:0; background:#f6f7fb; color:var(--ink); font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif; }
        .wrap { width:min(1080px,calc(100% - 32px)); margin:0 auto; padding:36px 0 56px; }
        header { display:flex; align-items:center; gap:16px; margin-bottom:26px; }
        header img { width:56px; height:56px; object-fit:contain; }
        h1 { margin:0; font-size:clamp(1.65rem,4vw,2.35rem); letter-spacing:-.035em; }
        .eyebrow { margin:0 0 4px; color:var(--brand); font-size:.78rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }
        .status { border:1px solid #bfdbfe; border-radius:18px; background:var(--busy-bg); padding:22px 24px; margin-bottom:24px; box-shadow:0 10px 32px rgba(16,24,40,.05); }
        .status.free { border-color:#a7f3d0; background:var(--ok-bg); }
        .status.off { border-color:#fecaca; background:var(--off-bg); }
        .status-label { margin:0 0 6px; color:var(--muted); font-size:.8rem; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
        .status-value { margin:0; color:var(--busy); font-size:clamp(1.2rem,3vw,1.65rem); font-weight:800; }
        .free .status-value { color:var(--ok); } .off .status-value { color:var(--off); }
        .status-time { margin:8px 0 0; color:var(--muted); font-size:.95rem; }
        .panel { overflow:hidden; border:1px solid var(--line); border-radius:18px; background:white; box-shadow:0 10px 32px rgba(16,24,40,.05); }
        .panel-head { padding:20px 24px; border-bottom:1px solid var(--line); }
        h2 { margin:0 0 4px; font-size:1.15rem; } .panel-head p { margin:0; color:var(--muted); font-size:.9rem; }
        table { width:100%; border-collapse:collapse; }
        th,td { padding:16px 24px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
        th { background:#fafafa; color:#475467; font-size:.76rem; letter-spacing:.06em; text-transform:uppercase; }
        td { font-size:.94rem; } tbody tr:last-child td { border-bottom:0; }
        .empty { padding:46px 24px; color:var(--muted); text-align:center; }
        .pagination { padding:16px 24px; border-top:1px solid var(--line); }
        .pagination nav > div:first-child { display:none; }
        .pagination nav > div:last-child { display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .pagination nav p { color:var(--muted); font-size:.85rem; }
        .pagination a,.pagination span { text-decoration:none; }
        @media (max-width:700px) { .wrap{width:min(100% - 20px,1080px);padding-top:22px} header{align-items:flex-start} .panel{overflow:visible}.table-wrap{overflow-x:auto} table{min-width:650px} th,td{padding:14px 16px}.pagination nav p{display:none} }
    </style>
</head>
<body>
<main class="wrap">
    <header>
        <img src="{{ asset('images/app/fav.png') }}" alt="Logo {{ config('app.name') }}">
        <div><p class="eyebrow">Jadwal Ruangan</p><h1>{{ $room->name }}</h1></div>
    </header>

    @if ($currentReservation)
        <section class="status" aria-label="Status ruangan saat ini">
            <p class="status-label">Status saat ini</p>
            <p class="status-value">Dipakai {{ $currentReservation->activity?->unit?->name ?? 'unit yang belum tercatat' }}</p>
            <p class="status-time">Sampai {{ $currentReservation->end_time->locale('id')->translatedFormat('l, d F Y · H.i') }} WIB</p>
        </section>
    @elseif (! $room->is_borrowable || ($room->status && $room->status !== 'Tersedia'))
        <section class="status off" aria-label="Status ruangan saat ini">
            <p class="status-label">Status saat ini</p>
            <p class="status-value">{{ $room->status ?: 'Tidak tersedia untuk dipinjam' }}</p>
        </section>
    @else
        <section class="status free" aria-label="Status ruangan saat ini">
            <p class="status-label">Status saat ini</p>
            <p class="status-value">Tidak ada pemakaian</p>
        </section>
    @endif

    <section class="panel">
        <div class="panel-head"><h2>Daftar Peminjaman</h2><p>Pemakaian yang sedang berlangsung dan jadwal berikutnya.</p></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nama Unit</th><th>Hari &amp; Jam Mulai</th><th>Hari &amp; Jam Selesai</th></tr></thead>
                <tbody>
                @forelse ($reservations as $reservation)
                    <tr>
                        <td>{{ $reservation->activity?->unit?->name ?? 'Unit tidak tercatat' }}</td>
                        <td>{{ $reservation->start_time->locale('id')->translatedFormat('l, d F Y · H.i') }} WIB</td>
                        <td>{{ $reservation->end_time->locale('id')->translatedFormat('l, d F Y · H.i') }} WIB</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Belum ada jadwal peminjaman ruangan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($reservations->hasPages())<div class="pagination">{{ $reservations->links() }}</div>@endif
    </section>
</main>
</body>
</html>
