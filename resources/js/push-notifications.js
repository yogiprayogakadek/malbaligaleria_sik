const root = document.querySelector('[data-validator-push]');

if (root) {
    const toggle = root.querySelector('[data-push-toggle]');
    const toggleLabel = toggle?.querySelector('span');
    const status = root.querySelector('[data-push-status]');
    const installButton = root.querySelector('[data-pwa-install]');
    const publicKey = root.dataset.pushPublicKey;
    const validatorId = root.dataset.validatorRealtime;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const ownerStorageKey = 'mbg-push-owner';
    let registration;
    let installPrompt;

    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const supported = window.isSecureContext
        && 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window
        && Boolean(publicKey)
        && Boolean(csrfToken);

    const setState = (message, enabled, subscribed = false) => {
        if (status) status.textContent = message;
        if (!toggle || !toggleLabel) return;

        toggle.disabled = !enabled;
        toggle.dataset.subscribed = String(subscribed);
        toggleLabel.textContent = subscribed ? 'Nonaktifkan' : 'Aktifkan';
    };

    const request = async (url, method, payload) => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);
    };

    const urlBase64ToUint8Array = value => {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(base64), character => character.charCodeAt(0));
    };

    const storeSubscription = async subscription => {
        const serialized = subscription.toJSON();

        await request(root.dataset.pushStoreUrl, 'POST', {
            endpoint: serialized.endpoint,
            keys: serialized.keys,
            content_encoding: PushManager.supportedContentEncodings?.[0] || 'aes128gcm',
        });
        sessionStorage.setItem(ownerStorageKey, validatorId);
    };

    const syncState = async () => {
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            if (sessionStorage.getItem(ownerStorageKey) !== validatorId) {
                try {
                    await storeSubscription(subscription);
                } catch (error) {
                    console.error('Gagal menyinkronkan pemilik langganan push.', error);
                    setState('Perlu diaktifkan ulang untuk akun ini', true);
                    return;
                }
            }

            setState('Aktif di perangkat ini', true, true);
            return;
        }

        if (Notification.permission === 'denied') {
            setState('Izin diblokir di pengaturan browser', false);
            return;
        }

        if (isIos && !isStandalone) {
            setState('Tambahkan ke Layar Utama untuk mengaktifkan', true);
            return;
        }

        setState('Belum aktif di perangkat ini', true);
    };

    const subscribe = async () => {
        if (isIos && !isStandalone) {
            window.showToast?.('Di iPhone atau iPad, pilih Bagikan lalu Tambahkan ke Layar Utama.', 'info');
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            await syncState();
            window.showToast?.('Izin notifikasi belum diberikan.', 'info');
            return;
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
        try {
            await storeSubscription(subscription);
        } catch (error) {
            await subscription.unsubscribe();
            throw error;
        }

        await syncState();
        window.showToast?.('Notifikasi perangkat berhasil diaktifkan.', 'info');
    };

    const unsubscribe = async subscription => {
        await request(root.dataset.pushDestroyUrl, 'DELETE', { endpoint: subscription.endpoint });
        await subscription.unsubscribe();
        sessionStorage.removeItem(ownerStorageKey);
        await syncState();
        window.showToast?.('Notifikasi perangkat dinonaktifkan.', 'info');
    };

    toggle?.addEventListener('click', async () => {
        toggle.disabled = true;

        try {
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) await unsubscribe(subscription);
            else await subscribe();
        } catch (error) {
            console.error('Gagal memperbarui langganan push.', error);
            setState('Tidak dapat mengubah pengaturan saat ini', true);
            window.showToast?.('Notifikasi perangkat gagal diperbarui. Coba lagi.', 'info');
        }
    });

    document.querySelectorAll('.logout-form, .staff-logout-form').forEach(form => {
        form.addEventListener('submit', async event => {
            if (!registration || form.dataset.pushCleanupDone === 'true') return;

            event.preventDefault();
            form.dataset.pushCleanupDone = 'true';

            try {
                const subscription = await registration.pushManager.getSubscription();
                if (subscription) {
                    try {
                        await request(root.dataset.pushDestroyUrl, 'DELETE', { endpoint: subscription.endpoint });
                    } finally {
                        await subscription.unsubscribe();
                        sessionStorage.removeItem(ownerStorageKey);
                    }
                }
            } catch (error) {
                console.error('Gagal membersihkan langganan push saat keluar.', error);
            } finally {
                form.submit();
            }
        });
    });

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        installPrompt = event;
        if (installButton && !isStandalone) installButton.hidden = false;
    });

    installButton?.addEventListener('click', async () => {
        if (!installPrompt) return;
        await installPrompt.prompt();
        installPrompt = undefined;
        installButton.hidden = true;
    });

    if (!supported) {
        setState(window.isSecureContext
            ? 'Tidak didukung oleh browser ini'
            : 'Memerlukan koneksi HTTPS', false);
    } else {
        navigator.serviceWorker.register('/sw.js', { scope: '/' })
            .then(serviceWorkerRegistration => {
                registration = serviceWorkerRegistration;
                return syncState();
            })
            .catch(error => {
                console.error('Service worker gagal didaftarkan.', error);
                setState('Layanan notifikasi tidak tersedia', false);
            });
    }
}
