(() => {
    if (window.PpasPwa) {
        return;
    }

    const configElement = document.getElementById('ppas-pwa-config');

    if (! configElement) {
        return;
    }

    const config = JSON.parse(configElement.textContent || '{}');
    const state = {
        installPrompt: null,
        registration: null,
        subscription: null,
        dismissed: sessionStorage.getItem('ppas-pwa-banner-dismissed') === '1',
    };

    const supportsPush = () => (
        'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window
    );

    const isStandalone = () => (
        window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true
    );

    const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const setVisible = (selector, visible) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.hidden = ! visible;
        });
    };

    const setStatus = (message) => {
        document.querySelectorAll('[data-pwa-status]').forEach((element) => {
            element.textContent = message;
        });
    };

    const urlBase64ToUint8Array = (value) => {
        const padding = '='.repeat((4 - (value.length % 4)) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = window.atob(base64);

        return Uint8Array.from([...raw].map((character) => character.charCodeAt(0)));
    };

    const request = async (url, method, body = null) => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: body === null ? null : JSON.stringify(body),
        });

        if (! response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || 'Permintaan notifikasi gagal.');
        }

        return response.status === 204 ? null : response.json();
    };

    const subscriptionPayload = (subscription) => {
        const json = subscription.toJSON();

        return {
            endpoint: subscription.endpoint,
            keys: json.keys || {},
            contentEncoding: window.PushManager.supportedContentEncodings?.[0] || 'aes128gcm',
        };
    };

    const ensureRegistration = async () => {
        if (! ('serviceWorker' in navigator)) {
            return null;
        }

        state.registration ??= await navigator.serviceWorker.register(config.serviceWorkerUrl, {
            scope: '/',
        });

        return navigator.serviceWorker.ready;
    };

    const syncExistingSubscription = async () => {
        if (! config.authenticated || ! supportsPush() || Notification.permission !== 'granted') {
            return;
        }

        const registration = await ensureRegistration();
        state.subscription = await registration.pushManager.getSubscription();

        if (state.subscription) {
            await request(config.subscribeUrl, 'POST', subscriptionPayload(state.subscription));
        }
    };

    const refreshUi = async () => {
        const pushSupported = supportsPush();
        const installed = isStandalone();
        const installAvailable = ! installed && (Boolean(state.installPrompt) || isIos());

        if (pushSupported && Notification.permission === 'granted' && ! state.subscription) {
            const registration = await ensureRegistration();
            state.subscription = await registration.pushManager.getSubscription();
        }

        const subscribed = Boolean(state.subscription);

        setVisible('[data-pwa-install-button]', installAvailable);
        setVisible('[data-pwa-enable-button]', config.authenticated && pushSupported && ! subscribed && Notification.permission !== 'denied');
        setVisible('[data-pwa-disable-button]', config.authenticated && subscribed);

        if (! pushSupported) {
            setStatus('Browser ini tidak mendukung Web Push.');
        } else if (Notification.permission === 'denied') {
            setStatus('Notifikasi diblokir. Izinkan kembali melalui pengaturan browser.');
        } else if (subscribed) {
            setStatus('Notifikasi aktif pada perangkat ini.');
        } else {
            setStatus('Aktifkan notifikasi untuk pembaruan peminjaman dan chat.');
        }

        const banner = document.getElementById('pwa-onboarding');
        if (banner) {
            const needsAttention = installAvailable || (pushSupported && ! subscribed);
            banner.hidden = state.dismissed || ! needsAttention;
        }
    };

    const enableNotifications = async () => {
        if (! supportsPush()) {
            throw new Error('Browser ini tidak mendukung Web Push.');
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            await refreshUi();
            return;
        }

        const registration = await ensureRegistration();
        const keyResponse = await request(config.vapidUrl, 'GET');
        state.subscription = await registration.pushManager.getSubscription();

        state.subscription ??= await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(keyResponse.publicKey),
        });

        await request(config.subscribeUrl, 'POST', subscriptionPayload(state.subscription));
        await refreshUi();
    };

    const disableNotifications = async () => {
        const registration = await ensureRegistration();
        const subscription = state.subscription || await registration.pushManager.getSubscription();

        if (! subscription) {
            state.subscription = null;
            await refreshUi();
            return;
        }

        try {
            await request(config.unsubscribeUrl, 'DELETE', { endpoint: subscription.endpoint });
        } finally {
            await subscription.unsubscribe();
            state.subscription = null;
            await refreshUi();
        }
    };

    const installApplication = async () => {
        if (state.installPrompt) {
            await state.installPrompt.prompt();
            await state.installPrompt.userChoice;
            state.installPrompt = null;
            await refreshUi();
            return;
        }

        if (isIos()) {
            window.alert('Di Safari, ketuk tombol Bagikan lalu pilih “Tambahkan ke Layar Utama”. Setelah terpasang, buka PPAS dari ikon tersebut.');
        }
    };

    const handleAction = async (action) => {
        try {
            setStatus('Memproses…');
            await action();
        } catch (error) {
            console.error(error);
            setStatus(error.message || 'Terjadi kesalahan. Silakan coba lagi.');
        }
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        state.installPrompt = event;
        refreshUi();
    });

    window.addEventListener('appinstalled', () => {
        state.installPrompt = null;
        refreshUi();
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (! button) {
            return;
        }

        if (button.matches('[data-pwa-install-button]')) {
            handleAction(installApplication);
        } else if (button.matches('[data-pwa-enable-button]')) {
            handleAction(enableNotifications);
        } else if (button.matches('[data-pwa-disable-button]')) {
            handleAction(disableNotifications);
        } else if (button.matches('[data-pwa-dismiss]')) {
            state.dismissed = true;
            sessionStorage.setItem('ppas-pwa-banner-dismissed', '1');
            refreshUi();
        }
    });

    document.addEventListener('livewire:navigated', refreshUi);

    window.PpasPwa = {
        enableNotifications,
        disableNotifications,
        installApplication,
        refreshUi,
    };

    ensureRegistration()
        .then(syncExistingSubscription)
        .catch((error) => console.error('PWA initialization failed:', error))
        .finally(refreshUi);
})();
