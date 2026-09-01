@if ($showBanner)
    <aside id="pwa-onboarding" hidden aria-live="polite">
        <button type="button" class="pwa-onboarding__close" data-pwa-dismiss aria-label="Tutup">&times;</button>
        <div class="pwa-onboarding__content">
            <strong>Gunakan PPAS sebagai aplikasi</strong>
            <span data-pwa-status>Memeriksa dukungan perangkat…</span>
        </div>
        <div class="pwa-onboarding__actions">
            <button type="button" data-pwa-install-button hidden>Install aplikasi</button>
            <button type="button" data-pwa-enable-button hidden>Aktifkan notifikasi</button>
            <button type="button" data-pwa-disable-button hidden>Nonaktifkan notifikasi</button>
        </div>
    </aside>
@endif

<script id="ppas-pwa-config" type="application/json">@json([
    'authenticated' => auth()->check(),
    'vapidUrl' => route('push.vapid-public-key'),
    'subscribeUrl' => route('push.subscriptions.store'),
    'unsubscribeUrl' => route('push.subscriptions.destroy'),
    'serviceWorkerUrl' => asset('sw.js'),
])</script>
<script src="{{ asset('js/pwa.js') }}" defer></script>

<style>
    #pwa-onboarding {
        position: fixed;
        z-index: 100;
        right: 1rem;
        bottom: 1rem;
        width: min(25rem, calc(100vw - 2rem));
        padding: 1rem;
        border: 1px solid rgb(199 210 254);
        border-radius: .9rem;
        background: rgb(255 255 255 / .98);
        box-shadow: 0 18px 45px rgb(15 23 42 / .2);
        color: rgb(15 23 42);
    }

    .pwa-onboarding__close {
        position: absolute;
        top: .35rem;
        right: .55rem;
        padding: .2rem .4rem;
        color: rgb(100 116 139);
        font-size: 1.4rem;
        line-height: 1;
    }

    .pwa-onboarding__content {
        display: grid;
        gap: .25rem;
        padding-right: 1.5rem;
    }

    .pwa-onboarding__content span {
        color: rgb(71 85 105);
        font-size: .875rem;
    }

    .pwa-onboarding__actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .85rem;
    }

    .pwa-onboarding__actions button {
        padding: .55rem .8rem;
        border-radius: .55rem;
        background: rgb(79 70 229);
        color: white;
        font-size: .8rem;
        font-weight: 600;
    }

    .pwa-onboarding__actions button[data-pwa-disable-button] {
        border: 1px solid rgb(203 213 225);
        background: white;
        color: rgb(51 65 85);
    }
</style>
