import { getEcho } from './echo';

const root = document.querySelector('[data-form-presence-admin]');

if (root) {
    const status = root.querySelector('[data-presence-status]');
    const updatedAt = root.querySelector('[data-presence-updated-at]');
    let syncing = false;

    const applySnapshot = (snapshot) => {
        Object.entries(snapshot.counts || {}).forEach(([type, count]) => {
            root.querySelectorAll(`[data-presence-count="${type}"]`).forEach(element => {
                element.textContent = new Intl.NumberFormat('id-ID').format(Number(count) || 0);
            });
        });
        root.querySelectorAll('[data-presence-total]').forEach(element => {
            element.textContent = new Intl.NumberFormat('id-ID').format(Number(snapshot.total) || 0);
        });
        if (updatedAt) {
            updatedAt.textContent = new Intl.DateTimeFormat('id-ID', {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
            }).format(new Date(snapshot.updated_at));
        }
    };

    const sync = async () => {
        if (syncing) return;
        syncing = true;
        try {
            const response = await fetch(root.dataset.snapshotUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (response.ok) applySnapshot(await response.json());
        } finally {
            syncing = false;
        }
    };

    try {
        const echo = getEcho();
        echo.private('admin.form-presence').listen('.form-presence.updated', applySnapshot);
        const connection = echo.connector?.pusher?.connection;
        connection?.bind('connected', () => {
            if (status) {
                status.textContent = 'Realtime aktif';
                status.classList.add('is-live');
            }
            sync();
        });
        connection?.bind('disconnected', () => {
            if (status) {
                status.textContent = 'Sinkronisasi berkala';
                status.classList.remove('is-live');
            }
        });
    } catch {
        if (status) status.textContent = 'Sinkronisasi berkala';
    }

    window.setInterval(sync, 15000);
    window.addEventListener('focus', sync);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') sync();
    });
}
