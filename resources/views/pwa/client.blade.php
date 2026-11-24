@php
    $pwaConfig = [
        'authenticated' => auth()->check(),
        'vapidUrl' => route('push.vapid-public-key'),
        'subscribeUrl' => route('push.subscriptions.store'),
        'unsubscribeUrl' => route('push.subscriptions.destroy'),
        'serviceWorkerUrl' => asset('sw.js'),
    ];
@endphp

<script id="ppas-pwa-config" type="application/json">
    @json($pwaConfig)
</script>

<script src="{{ asset('js/pwa.js') }}" defer></script>

<style>
    #pwa-onboarding {
        /* Keep onboarding inside the page layout; never cover form actions. */
        position: relative;
        box-sizing: border-box;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        margin-block: .75rem 1rem;
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
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: .5rem;
        color: rgb(100 116 139);
        font-size: 1.4rem;
        line-height: 1;
    }

    .pwa-onboarding__close:hover,
    .pwa-onboarding__close:focus-visible {
        background: rgb(241 245 249);
        color: rgb(15 23 42);
    }

    .pwa-onboarding__content {
        display: grid;
        gap: .25rem;
        min-width: 0;
        padding-right: 2.75rem;
        overflow-wrap: anywhere;
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