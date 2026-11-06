/* Livewire 3 request hook: handle session mismatch without native confirm(). */
(() => {
    'use strict';

    // A wire:navigate visit can re-run scripts without a full page reload.
    if (window.__invmanSessionRecoveryInstalled) return;
    window.__invmanSessionRecoveryInstalled = true;

    let hooked = false;

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
