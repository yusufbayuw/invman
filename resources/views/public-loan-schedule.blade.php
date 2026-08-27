<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Jadwal Peminjaman | {{ config('app.name') }}</title>
    <style>
        :root {
            --ink: #17263c;
            --muted: #66758a;
            --line: #dce4ee;
            --paper: #ffffff;
            --canvas: #f3f6fa;
            --brand: #155eef;
            --brand-dark: #0d3ca6;
            --active: #087a55;
            --active-bg: #dcfaec;
            --approved: #315bb7;
            --approved-bg: #e9efff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--ink);
            background:
                radial-gradient(circle at 90% 0, rgba(21, 94, 239, .10), transparent 24rem),
                var(--canvas);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.5;
        }

        a { color: inherit; }

        .wrap { width: min(1180px, calc(100% - 32px)); margin-inline: auto; }

        .topbar {
            border-bottom: 1px solid rgba(220, 228, 238, .85);
            background: rgba(255, 255, 255, .88);
            backdrop-filter: blur(12px);
        }

        .topbar-inner {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand { display: flex; align-items: center; gap: 12px; font-weight: 800; }
        .brand img { width: 42px; height: 42px; object-fit: contain; }
        .brand small { display: block; color: var(--muted); font-size: 12px; font-weight: 600; }

        .login {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 9px 14px;
            background: var(--paper);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
        }

        .login:hover { border-color: var(--brand); color: var(--brand); }

        .hero { padding: 54px 0 30px; }
        .eyebrow { color: var(--brand); font-size: 13px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        h1 { max-width: 760px; margin: 8px 0 12px; font-size: clamp(32px, 5vw, 54px); line-height: 1.08; letter-spacing: -.035em; }
        .lead { max-width: 710px; margin: 0; color: var(--muted); font-size: 17px; }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin: 28px 0 38px;
        }

        .summary-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255, 255, 255, .9);
            box-shadow: 0 8px 24px rgba(38, 56, 82, .05);
        }

        .summary-icon {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            border-radius: 12px;
            color: var(--brand);
            background: #edf3ff;
            font-size: 21px;
        }

        .summary-number { display: block; font-size: 24px; font-weight: 850; line-height: 1; }
        .summary-label { color: var(--muted); font-size: 13px; }

        .section { margin-bottom: 36px; }
        .section-heading { display: flex; align-items: end; justify-content: space-between; gap: 16px; margin-bottom: 12px; }
        h2 { margin: 0; font-size: 22px; letter-spacing: -.015em; }
        .section-count { color: var(--muted); font-size: 13px; }

        .schedule {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--paper);
            box-shadow: 0 9px 25px rgba(38, 56, 82, .045);
        }

        .row {
            display: grid;
            grid-template-columns: minmax(210px, 1.35fr) minmax(150px, .9fr) minmax(250px, 1.25fr) 140px;
            align-items: center;
            gap: 20px;
            padding: 18px 20px;
            border-top: 1px solid var(--line);
        }

        .row:first-child { border-top: 0; }
        .asset-name { font-weight: 800; }
        .detail, .label { color: var(--muted); font-size: 13px; }
        .unit-name, .time { margin-top: 2px; font-size: 14px; font-weight: 650; }
        .time span { display: block; }

        .badge {
            justify-self: end;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            width: max-content;
            padding: 6px 10px;
            border-radius: 999px;
            color: var(--approved);
            background: var(--approved-bg);
            font-size: 12px;
            font-weight: 800;
        }

        .badge.active { color: var(--active); background: var(--active-bg); }
        .badge-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }

        .empty { padding: 34px 22px; color: var(--muted); text-align: center; }
        .empty strong { display: block; margin-bottom: 4px; color: var(--ink); }

        footer { padding: 10px 0 40px; color: var(--muted); font-size: 12px; text-align: center; }

        @media (max-width: 820px) {
            .summary { grid-template-columns: 1fr; }
            .row { grid-template-columns: 1fr 1fr; }
            .row-status { align-self: end; }
            .badge { justify-self: start; }
        }

        @media (max-width: 540px) {
            .wrap { width: min(100% - 22px, 1180px); }
            .topbar-inner { min-height: 64px; }
            .brand small { display: none; }
            .hero { padding-top: 36px; }
            .summary { margin-bottom: 30px; }
            .row { grid-template-columns: 1fr; gap: 13px; padding: 17px; }
            .section-heading { align-items: start; flex-direction: column; gap: 2px; }
        }
    </style>
</head>
<body>
    @php
        $isActive = fn ($reservation) => $reservation->status === \App\Enums\ReservationStatus::CheckedOut->value
            || ($reservation->start_time && $reservation->end_time && $now->between($reservation->start_time, $reservation->end_time));
        $date = fn ($value) => $value?->locale('id')->translatedFormat('d M Y, H:i') ?? '-';
        $unit = fn ($reservation) => $reservation->activity?->unit?->name ?? 'Unit tidak tercatat';
    @endphp

    <header class="topbar">
        <div class="wrap topbar-inner">
            <div class="brand">
                <img src="{{ asset(config('app.logo')) }}" alt="Logo {{ config('app.name') }}">
                <div>{{ config('app.name') }} <small>Portal Peminjaman Aset &amp; Sarpras</small></div>
            </div>
            <a class="login" href="{{ url('/admin') }}">Masuk aplikasi</a>
        </div>
    </header>

    <main class="wrap">
        <section class="hero" aria-labelledby="page-title">
            <div class="eyebrow">Informasi ketersediaan</div>
            <h1 id="page-title">Jadwal Peminjaman</h1>
            <p class="lead">Daftar barang, ruangan, dan kendaraan yang telah disetujui atau sedang digunakan. Jadwal yang sudah selesai tidak ditampilkan.</p>

            <div class="summary" aria-label="Ringkasan jadwal">
                <div class="summary-card">
                    <div class="summary-icon" aria-hidden="true">▣</div>
                    <div><span class="summary-number">{{ $itemReservations->count() }}</span><span class="summary-label">Peminjaman barang</span></div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" aria-hidden="true">⌂</div>
                    <div><span class="summary-number">{{ $roomReservations->count() }}</span><span class="summary-label">Peminjaman ruangan</span></div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" aria-hidden="true">◆</div>
                    <div><span class="summary-number">{{ $vehicleReservations->count() }}</span><span class="summary-label">Peminjaman kendaraan</span></div>
                </div>
            </div>
        </section>

        <section class="section" aria-labelledby="items-title">
            <div class="section-heading"><h2 id="items-title">Barang &amp; Peralatan</h2><span class="section-count">{{ $itemReservations->count() }} jadwal aktif/mendatang</span></div>
            <div class="schedule">
                @forelse ($itemReservations as $reservation)
                    <article class="row">
                        <div><div class="asset-name">{{ $reservation->item?->name ?? 'Barang tidak tercatat' }}</div><div class="detail">Jumlah: {{ $reservation->quantity ?? 1 }}</div></div>
                        <div><div class="label">Unit peminjam</div><div class="unit-name">{{ $unit($reservation) }}</div></div>
                        <div><div class="label">Jadwal penggunaan</div><div class="time"><span>{{ $date($reservation->start_time) }}</span><span>s.d. {{ $date($reservation->end_time) }}</span></div></div>
                        <div class="row-status"><span class="badge {{ $isActive($reservation) ? 'active' : '' }}"><span class="badge-dot"></span>{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}</span></div>
                    </article>
                @empty
                    <div class="empty"><strong>Belum ada jadwal barang</strong>Tidak ada peminjaman barang yang disetujui atau sedang berjalan.</div>
                @endforelse
            </div>
        </section>

        <section class="section" aria-labelledby="rooms-title">
            <div class="section-heading"><h2 id="rooms-title">Ruangan &amp; Tempat</h2><span class="section-count">{{ $roomReservations->count() }} jadwal aktif/mendatang</span></div>
            <div class="schedule">
                @forelse ($roomReservations as $reservation)
                    <article class="row">
                        <div><div class="asset-name">{{ $reservation->room?->name ?? 'Ruangan tidak tercatat' }}</div><div class="detail">{{ collect([$reservation->room?->floor?->building?->name, $reservation->room?->floor?->name])->filter()->join(' · ') ?: 'Lokasi belum tercatat' }}</div></div>
                        <div><div class="label">Unit peminjam</div><div class="unit-name">{{ $unit($reservation) }}</div></div>
                        <div><div class="label">Jadwal penggunaan</div><div class="time"><span>{{ $date($reservation->start_time) }}</span><span>s.d. {{ $date($reservation->end_time) }}</span></div></div>
                        <div class="row-status"><span class="badge {{ $isActive($reservation) ? 'active' : '' }}"><span class="badge-dot"></span>{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}</span></div>
                    </article>
                @empty
                    <div class="empty"><strong>Belum ada jadwal ruangan</strong>Tidak ada peminjaman ruangan yang disetujui atau sedang berjalan.</div>
                @endforelse
            </div>
        </section>

        <section class="section" aria-labelledby="vehicles-title">
            <div class="section-heading"><h2 id="vehicles-title">Kendaraan</h2><span class="section-count">{{ $vehicleReservations->count() }} jadwal aktif/mendatang</span></div>
            <div class="schedule">
                @forelse ($vehicleReservations as $reservation)
                    <article class="row">
                        <div><div class="asset-name">{{ $reservation->vehicle?->name ?? 'Kendaraan tidak tercatat' }}</div><div class="detail">{{ $reservation->vehicle?->license_plate ?: 'Nomor polisi belum tercatat' }}</div></div>
                        <div><div class="label">Unit peminjam</div><div class="unit-name">{{ $unit($reservation) }}</div></div>
                        <div><div class="label">Jadwal penggunaan</div><div class="time"><span>{{ $date($reservation->start_time) }}</span><span>s.d. {{ $date($reservation->end_time) }}</span></div></div>
                        <div class="row-status"><span class="badge {{ $isActive($reservation) ? 'active' : '' }}"><span class="badge-dot"></span>{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}</span></div>
                    </article>
                @empty
                    <div class="empty"><strong>Belum ada jadwal kendaraan</strong>Tidak ada peminjaman kendaraan yang disetujui atau sedang berjalan.</div>
                @endforelse
            </div>
        </section>
    </main>

    <footer class="wrap">Diperbarui {{ $now->locale('id')->translatedFormat('d F Y, H:i') }} WIB</footer>
</body>
</html>
