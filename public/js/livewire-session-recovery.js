/* Preserve forms, distinguish expired sessions from CSRF, and never replay an executed write. */
(() => {
    'use strict';
    if (window.__invmanSessionRecoveryInstalled) return;
    window.__invmanSessionRecoveryInstalled = true;
    const originalFetch = window.fetch.bind(window);
    let hooked = false;

    const noticeElement = () => document.getElementById('invman-session-expired');
    const failure = (status, issue) => new Response(JSON.stringify({ invman_session_issue: issue }), {
        status, headers: { 'Content-Type': 'application/json' },
    });
    const checkSession = async (panel) => {
        let response;
        try {
            response = await originalFetch(panel.dataset.sessionUrl, {
                credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
            });
        } catch {
            return { error: failure(503, 'check-failed') };
        }
        if (!response.ok) return { error: failure(response.status, response.status === 401 ? 'expired' : response.status === 403 ? 'access-denied' : response.status === 423 ? 'license-locked' : 'check-failed') };
        let session;
        try { session = await response.json(); }
        catch { return { error: failure(503, 'check-failed') }; }
        if (typeof session.user !== 'string' || typeof session.token !== 'string' || !session.token) {
            return { error: failure(503, 'check-failed') };
        }
        if (session.user !== panel.dataset.sessionUser) {
            return { error: failure(401, session.user === '' ? 'expired' : 'account-changed') };
        }
        return { token: session.token };
    };

    window.fetch = async (input, options) => {
        const headers = new Headers(options?.headers);
        if (options?.method?.toUpperCase() !== 'POST' || !headers.has('X-Livewire')) return originalFetch(input, options);
        const target = new URL(typeof input === 'string' ? input : (input?.url ?? String(input)), location.href);
        const panel = noticeElement();
        if (target.origin !== location.origin || !panel?.dataset.sessionUrl) return originalFetch(input, options);
        const session = await checkSession(panel);
        if (session.error) return session.error;
        const payload = JSON.parse(options.body);
        const send = (token) => {
            // Uploads and other Livewire helpers read these DOM sources later.
            // Refreshing only the JSON payload would leave their token stale.
            document.querySelectorAll?.('meta[name="csrf-token"]').forEach(node => node.setAttribute('content', token));
            document.querySelectorAll?.('[data-csrf]').forEach(node => node.setAttribute('data-csrf', token));
            if (window.livewireScriptConfig) window.livewireScriptConfig.csrf = token;
            payload._token = token;
            if (headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', token);
            return originalFetch(input, { ...options, headers, body: JSON.stringify(payload) });
        };
        let response = await send(session.token);
        // A marked Laravel CSRF rejection cannot have executed the operation.
        // Never retry unmarked 419s, network errors, validation errors or other failures.
        if (response.status === 419 && response.headers.get('X-Invman-CSRF-Rejected') === '1') {
            const refreshed = await checkSession(panel);
            if (refreshed.error) return refreshed.error;
            if (refreshed.token !== session.token) response = await send(refreshed.token);
        }
        return response;
    };

    const showNotice = (issue) => {
        const notice = noticeElement();
        if (!notice) return;
        const messages = {
            expired: ['Sesi masuk sudah berakhir', 'Masuk kembali untuk melanjutkan. Isian formulir di halaman ini tetap dipertahankan.'],
            'component-release': ['Tampilan aplikasi perlu dimuat ulang', 'Komponen halaman tidak dikenali atau versi aplikasi berubah. Salin isian penting lalu muat ulang halaman. Ini bukan penolakan token CSRF.'],
            'account-changed': ['Akun berubah di tab lain', 'Halaman ini berasal dari akun sebelumnya. Muat ulang sebelum melanjutkan agar tindakan memakai akun yang benar.'],
            'check-failed': ['Pemeriksaan sesi belum berhasil', 'Sistem belum dapat memeriksa sesi. Periksa koneksi lalu coba lagi. Ini bukan bukti kesalahan token CSRF.'],
            'access-denied': ['Pemeriksaan sesi ditolak', 'Server menolak akses pemeriksaan sesi (403). Hubungi administrator jika akun Anda seharusnya memiliki akses.'],
            'license-locked': ['Aplikasi belum dapat digunakan', 'Server menolak permintaan karena status aktivasi aplikasi (423). Hubungi administrator.'],
            csrf: ['Halaman perlu diperbarui', 'Server mengembalikan kode 419. Periksa sesi dan status transaksi sebelum memuat ulang atau mengulangi tindakan.'],
        };
        const [title, message] = messages[issue] || messages.csrf;
        const heading = document.getElementById('invman-session-expired-title');
        if (heading) heading.textContent = title;
        const paragraph = notice.querySelector('[data-invman-session-message]');
        if (paragraph) paragraph.textContent = message;
        const login = notice.querySelector('[data-invman-session-login]');
        if (login) login.hidden = issue !== 'expired';
        if (notice.hidden) {
            notice.hidden = false;
            notice.querySelector('[data-invman-session-reload]')?.focus({ preventScroll: true });
        }
    };
    const registerHook = () => {
        if (hooked || typeof window.Livewire?.hook !== 'function') return;
        hooked = true;
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, content, preventDefault }) => {
                let issue;
                try { issue = JSON.parse(content)?.invman_session_issue; } catch { /* Non-JSON server response. */ }
                if (status !== 419 && !issue) return;
                preventDefault();
                showNotice(issue || 'csrf');
            });
        });
    };
    document.addEventListener('livewire:init', registerHook);
    document.addEventListener('livewire:navigated', registerHook);
    document.addEventListener('click', (event) => {
        if (event.target?.closest?.('[data-invman-session-reload]')) window.location.reload();
    });
    registerHook();
})();
