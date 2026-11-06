{{-- This panel-level notice intentionally leaves unsaved form inputs visible. --}}
<aside id="invman-session-expired" hidden role="alert" aria-live="assertive" aria-labelledby="invman-session-expired-title">
    <h2 id="invman-session-expired-title">Sesi halaman perlu diperbarui</h2>
    <p>Permintaan terakhir tidak dapat diproses (419). Anda mungkin perlu masuk kembali.</p>
    <p>Jika ada isian yang belum disimpan, salin dahulu sebelum memuat ulang. Periksa status transaksi sebelum mengulangi suatu tindakan.</p>
    <button type="button" data-invman-session-reload>Muat ulang halaman</button>
</aside>

<style>
    #invman-session-expired[hidden] { display: none !important; }
    #invman-session-expired {
        position: fixed;
        z-index: 9999;
        top: 1rem;
        right: 1rem;
        width: min(27rem, calc(100vw - 2rem));
        padding: 1rem 1.1rem;
        border: 1px solid #fca5a5;
        border-radius: .8rem;
        box-shadow: 0 15px 40px rgba(15, 23, 42, .18);
        background: #fff;
        color: #1f2937;
        font: inherit;
    }
    #invman-session-expired h2 { margin: 0 0 .45rem; font-size: 1rem; font-weight: 700; }
    #invman-session-expired p { margin: .45rem 0; font-size: .875rem; line-height: 1.5; }
    #invman-session-expired button {
        margin-top: .55rem;
        padding: .55rem .85rem;
        border-radius: .5rem;
        background: #4f46e5;
        color: #fff;
        font-size: .875rem;
        font-weight: 600;
        cursor: pointer;
    }
    #invman-session-expired button:focus-visible { outline: 3px solid #818cf8; outline-offset: 2px; }
</style>
<script src="{{ asset('js/livewire-session-recovery.js') }}" defer></script>
