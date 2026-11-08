/* Livewire 3 request hook: handle session mismatch without native confirm(). */
(() => {
    'use strict';

    // A wire:navigate visit can re-run scripts without a full page reload.
    if (window.__invmanSessionRecoveryInstalled) return;
    window.__invmanSessionRecoveryInstalled = true;

    let hooked = false;

    const originalFetch = window.fetch.bind(window);
    // Livewire's request hook is synchronous. Scope this async preflight to its
    // JSON updates only, leaving uploads, chat, and other fetch calls unchanged.
    window.fetch = async (input, options) => {
        const headers = new Headers(options?.headers);
        if (options?.method !== 'POST' || !headers.has('X-Livewire')) {
            return originalFetch(input, options);
        }
        const target = new URL(typeof input === 'string' ? input : input.url, location.href);
        const panel = document.getElementById('invman-session-expired');
        if (target.origin !== location.origin || !panel?.dataset.sessionUrl) {
            return originalFetch(input, options);
        }

        const status = await originalFetch(panel.dataset.sessionUrl, {
            credentials: 'same-origin', cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!status.ok) return new Response('', { status: 419 });
        const session = await status.json();
        // Another tab may have signed out or changed accounts. Never submit an
        // old form under a different identity, even with a valid fresh token.
        if (session.user !== panel.dataset.sessionUser || !session.token) {
            return new Response('', { status: 419 });
        }
        const payload = JSON.parse(options.body);
        payload._token = session.token;
        if (headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', session.token);
        return originalFetch(input, { ...options, headers, body: JSON.stringify(payload) });
    };

    const showNotice = () => {
        const notice = document.getElementById('invman-session-expired');
        if (!notice || !notice.hidden) return;

        notice.hidden = false;
        notice.querySelector('[data-invman-session-reload]')?.focus({ preventScroll: true });
    };

    const registerHook = () => {
        if (hooked || typeof window.Livewire?.hook !== 'function') return;
        hooked = true;

        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status !== 419) return;

                // Do not retry writes or reload automatically: the user might
                // have form inputs or an in-progress transaction to inspect.
                preventDefault();
                showNotice();
            });
        });
    };

    document.addEventListener('livewire:init', registerHook);
    document.addEventListener('livewire:navigated', registerHook);
    document.addEventListener('click', (event) => {
        if (event.target?.closest?.('[data-invman-session-reload]')) {
            window.location.reload();
        }
    });

    // Handles installations where Livewire has already initialized.
    registerHook();
})();
