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
            min-height: 94px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand { display: inline-flex; align-items: center; }
        .brand img { width: 92px; height: 66px; object-fit: contain; object-position: left center; }

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
            width: 100%;
            display: grid;
            grid-template-columns: minmax(210px, 1.35fr) minmax(150px, .9fr) minmax(250px, 1.25fr) 140px;
            align-items: center;
            gap: 20px;
            padding: 18px 20px;
            border-top: 1px solid var(--line);
            border-right: 0;
            border-bottom: 0;
            border-left: 0;
            color: inherit;
            background: transparent;
            font: inherit;
            text-align: left;
            cursor: pointer;
            transition: background .2s ease, transform .2s ease;
        }

        .row:first-child { border-top: 0; }
        .row:hover { background: #f7faff; }
        .row:focus-visible { position: relative; z-index: 1; outline: 3px solid rgba(21, 94, 239, .25); outline-offset: -3px; }
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

        .row-status { display: flex; align-items: center; justify-content: flex-end; gap: 10px; }
        .open-detail { color: var(--brand); font-size: 20px; line-height: 1; transition: transform .2s ease; }
        .row:hover .open-detail { transform: translateX(3px); }

        dialog {
            width: min(560px, calc(100% - 28px));
            padding: 0;
            overflow: hidden;
            border: 0;
            border-radius: 24px;
            color: var(--ink);
            background: var(--paper);
            box-shadow: 0 28px 80px rgba(12, 29, 58, .25);
        }
        dialog::backdrop { background: rgba(13, 27, 48, .58); backdrop-filter: blur(5px); }
        .modal-head { position: relative; padding: 28px 28px 23px; color: white; background: linear-gradient(135deg, #0d3ca6, #1768f2); }
        .modal-kicker { margin-bottom: 7px; color: rgba(255,255,255,.72); font-size: 12px; font-weight: 800; letter-spacing: .09em; text-transform: uppercase; }
        .modal-head h3 { max-width: calc(100% - 44px); margin: 0; font-size: 25px; line-height: 1.2; letter-spacing: -.02em; }
        .modal-close { position: absolute; top: 18px; right: 18px; width: 38px; height: 38px; border: 1px solid rgba(255,255,255,.25); border-radius: 50%; color: white; background: rgba(255,255,255,.12); font-size: 24px; line-height: 1; cursor: pointer; }
        .modal-body { padding: 26px 28px 28px; }
        .modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px 24px; }
        .modal-field.wide { grid-column: 1 / -1; }
        .modal-label { display: block; margin-bottom: 4px; color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
        .modal-value { font-size: 15px; font-weight: 750; }
        .modal-status { margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .modal-hint { color: var(--muted); font-size: 12px; }

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
            .brand img { width: 78px; height: 56px; }
            .hero { padding-top: 36px; }
            .summary { margin-bottom: 30px; }
            .row { grid-template-columns: 1fr; gap: 13px; padding: 17px; }
            .section-heading { align-items: start; flex-direction: column; gap: 2px; }
            .modal-grid { grid-template-columns: 1fr; gap: 16px; }
            .modal-field.wide { grid-column: auto; }
            .modal-head, .modal-body { padding-left: 22px; padding-right: 22px; }
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
            <a class="brand" href="{{ url('/') }}" aria-label="Beranda {{ config('app.name') }}">
                <img src="{{ asset(config('app.logo')) }}" alt="Logo {{ config('app.name') }}">
            </a>
            <a class="login" href="{{ url('/admin') }}">Masuk aplikasi</a>
        </div>
    </header>

    <main class="wrap">
        <section class="hero" aria-labelledby="page-title">
            <div class="eyebrow">Informasi ketersediaan</div>
            <h1 id="page-title">Jadwal Peminjaman</h1>

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
                    <button type="button" class="row js-detail" data-type="Barang &amp; Peralatan" data-name="{{ $reservation->item?->name ?? 'Barang tidak tercatat' }}" data-extra-label="Jumlah" data-extra="{{ $reservation->quantity ?? 1 }} unit" data-event-name="{{ $reservation->activity?->name ?: 'Nama acara belum tercatat' }}" data-event-description="{{ $reservation->activity?->description ?: 'Keterangan acara belum tersedia.' }}" data-unit="{{ $unit($reservation) }}" data-start="{{ $date($reservation->start_time) }}" data-end="{{ $date($reservation->end_time) }}" data-status="{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}" aria-label="Lihat detail {{ $reservation->item?->name ?? 'barang' }}">
                        <div><div class="asset-name">{{ $reservation->item?->name ?? 'Barang tidak tercatat' }}</div><div class="detail">Jumlah: {{ $reservation->quantity ?? 1 }}</div></div>
                        <div><div class="label">Unit peminjam</div><div class="unit-name">{{ $unit($reservation) }}</div></div>
                        <div><div class="label">Jadwal penggunaan</div><div class="time"><span>{{ $date($reservation->start_time) }}</span><span>s.d. {{ $date($reservation->end_time) }}</span></div></div>
                        <div class="row-status"><span class="badge {{ $isActive($reservation) ? 'active' : '' }}"><span class="badge-dot"></span>{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}</span><span class="open-detail" aria-hidden="true">→</span></div>
                    </button>
                @empty
                    <div class="empty"><strong>Belum ada jadwal barang</strong>Tidak ada peminjaman barang yang disetujui atau sedang berjalan.</div>
                @endforelse
            </div>
        </section>

        <section class="section" aria-labelledby="rooms-title">
            <div class="section-heading"><h2 id="rooms-title">Ruangan &amp; Tempat</h2><span class="section-count">{{ $roomReservations->count() }} jadwal aktif/mendatang</span></div>
            <div class="schedule">
                @forelse ($roomReservations as $reservation)
                    <button type="button" class="row js-detail" data-type="Ruangan &amp; Tempat" data-name="{{ $reservation->room?->name ?? 'Ruangan tidak tercatat' }}" data-extra-label="Lokasi" data-extra="{{ collect([$reservation->room?->floor?->building?->name, $reservation->room?->floor?->name])->filter()->join(' · ') ?: 'Lokasi belum tercatat' }}" data-event-name="{{ $reservation->activity?->name ?: 'Nama acara belum tercatat' }}" data-event-description="{{ $reservation->activity?->description ?: 'Keterangan acara belum tersedia.' }}" data-unit="{{ $unit($reservation) }}" data-start="{{ $date($reservation->start_time) }}" data-end="{{ $date($reservation->end_time) }}" data-status="{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}" aria-label="Lihat detail {{ $reservation->room?->name ?? 'ruangan' }}">
                        <div><div class="asset-name">{{ $reservation->room?->name ?? 'Ruangan tidak tercatat' }}</div><div class="detail">{{ collect([$reservation->room?->floor?->building?->name, $reservation->room?->floor?->name])->filter()->join(' · ') ?: 'Lokasi belum tercatat' }}</div></div>
                        <div><div class="label">Unit peminjam</div><div class="unit-name">{{ $unit($reservation) }}</div></div>
                        <div><div class="label">Jadwal penggunaan</div><div class="time"><span>{{ $date($reservation->start_time) }}</span><span>s.d. {{ $date($reservation->end_time) }}</span></div></div>
                        <div class="row-status"><span class="badge {{ $isActive($reservation) ? 'active' : '' }}"><span class="badge-dot"></span>{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}</span><span class="open-detail" aria-hidden="true">→</span></div>
                    </button>
                @empty
                    <div class="empty"><strong>Belum ada jadwal ruangan</strong>Tidak ada peminjaman ruangan yang disetujui atau sedang berjalan.</div>
                @endforelse
            </div>
        </section>

        <section class="section" aria-labelledby="vehicles-title">
            <div class="section-heading"><h2 id="vehicles-title">Kendaraan</h2><span class="section-count">{{ $vehicleReservations->count() }} jadwal aktif/mendatang</span></div>
            <div class="schedule">
                @forelse ($vehicleReservations as $reservation)
                    <button type="button" class="row js-detail" data-type="Kendaraan" data-name="{{ $reservation->vehicle?->name ?? 'Kendaraan tidak tercatat' }}" data-extra-label="Nomor polisi" data-extra="{{ $reservation->vehicle?->license_plate ?: 'Nomor polisi belum tercatat' }}" data-event-name="{{ $reservation->activity?->name ?: 'Nama acara belum tercatat' }}" data-event-description="{{ $reservation->activity?->description ?: 'Keterangan acara belum tersedia.' }}" data-unit="{{ $unit($reservation) }}" data-start="{{ $date($reservation->start_time) }}" data-end="{{ $date($reservation->end_time) }}" data-status="{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}" aria-label="Lihat detail {{ $reservation->vehicle?->name ?? 'kendaraan' }}">
                        <div><div class="asset-name">{{ $reservation->vehicle?->name ?? 'Kendaraan tidak tercatat' }}</div><div class="detail">{{ $reservation->vehicle?->license_plate ?: 'Nomor polisi belum tercatat' }}</div></div>
                        <div><div class="label">Unit peminjam</div><div class="unit-name">{{ $unit($reservation) }}</div></div>
                        <div><div class="label">Jadwal penggunaan</div><div class="time"><span>{{ $date($reservation->start_time) }}</span><span>s.d. {{ $date($reservation->end_time) }}</span></div></div>
                        <div class="row-status"><span class="badge {{ $isActive($reservation) ? 'active' : '' }}"><span class="badge-dot"></span>{{ $isActive($reservation) ? 'Sedang Berjalan' : 'Disetujui' }}</span><span class="open-detail" aria-hidden="true">→</span></div>
                    </button>
                @empty
                    <div class="empty"><strong>Belum ada jadwal kendaraan</strong>Tidak ada peminjaman kendaraan yang disetujui atau sedang berjalan.</div>
                @endforelse
            </div>
        </section>
    </main>

    <dialog id="detail-modal" aria-labelledby="modal-title">
        <div class="modal-head">
            <div class="modal-kicker" id="modal-type"></div>
            <h3 id="modal-title"></h3>
            <button type="button" class="modal-close" aria-label="Tutup detail">×</button>
        </div>
        <div class="modal-body">
            <div class="modal-grid">
                <div class="modal-field wide"><span class="modal-label" id="modal-extra-label"></span><div class="modal-value" id="modal-extra"></div></div>
                <div class="modal-field wide"><span class="modal-label">Nama acara</span><div class="modal-value" id="modal-event-name"></div></div>
                <div class="modal-field wide"><span class="modal-label">Keterangan acara</span><div class="modal-value" id="modal-event-description"></div></div>
                <div class="modal-field"><span class="modal-label">Unit peminjam</span><div class="modal-value" id="modal-unit"></div></div>
                <div class="modal-field"><span class="modal-label">Mulai digunakan</span><div class="modal-value" id="modal-start"></div></div>
                <div class="modal-field wide"><span class="modal-label">Selesai digunakan</span><div class="modal-value" id="modal-end"></div></div>
            </div>
            <div class="modal-status"><span class="modal-hint">Informasi jadwal publik</span><span class="badge" id="modal-badge"><span class="badge-dot"></span><span id="modal-status-text"></span></span></div>
        </div>
    </dialog>

    <footer class="wrap">Diperbarui {{ $now->locale('id')->translatedFormat('d F Y, H:i') }} WIB</footer>
    <script>
        const modal = document.getElementById('detail-modal');
        const fields = ['type', 'title', 'extra-label', 'extra', 'unit', 'start', 'end'];

        document.querySelectorAll('.js-detail').forEach((row) => {
            row.addEventListener('click', () => {
                const data = row.dataset;
                document.getElementById('modal-type').textContent = data.type;
                document.getElementById('modal-title').textContent = data.name;
                document.getElementById('modal-extra-label').textContent = data.extraLabel;
                document.getElementById('modal-extra').textContent = data.extra;
                document.getElementById('modal-event-name').textContent = data.eventName;
                document.getElementById('modal-event-description').textContent = data.eventDescription;
                document.getElementById('modal-unit').textContent = data.unit;
                document.getElementById('modal-start').textContent = data.start;
                document.getElementById('modal-end').textContent = data.end;
                document.getElementById('modal-status-text').textContent = data.status;
                document.getElementById('modal-badge').classList.toggle('active', data.status === 'Sedang Berjalan');
                modal.showModal();
            });
        });

        modal.querySelector('.modal-close').addEventListener('click', () => modal.close());
        modal.addEventListener('click', (event) => {
            if (event.target === modal) modal.close();
        });
    </script>
</body>
</html>
