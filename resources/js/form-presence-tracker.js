const tracker = document.querySelector('[data-form-presence-tracker]');

if (tracker) {
    const storageKey = `mbg-form-presence:${tracker.dataset.formType}:tab`;
    const visitorStorageKey = 'mbg-form-presence:visitor';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let tabId = readStorage(sessionStorage, storageKey);
    let visitorId = readStorage(localStorage, visitorStorageKey);
    let heartbeatTimer;
    let heartbeatPending = false;
    let registered = false;

    if (!tabId) {
        tabId = createUuid();
        writeStorage(sessionStorage, storageKey, tabId);
    }
    if (!visitorId) {
        visitorId = createUuid();
        writeStorage(localStorage, visitorStorageKey, visitorId);
    }

    const heartbeat = async () => {
        if (document.visibilityState !== 'visible' || heartbeatPending) return;

        heartbeatPending = true;
        try {
            const response = await fetch(tracker.dataset.heartbeatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ tab_id: tabId, visitor_id: visitorId }),
            });
            registered = response.ok;
        } catch {
            registered = false;
        } finally {
            heartbeatPending = false;
        }
    };

    const leave = () => {
        if (!registered || !navigator.sendBeacon) return;

        const data = new FormData();
        data.append('_token', csrfToken);
        data.append('tab_id', tabId);
        data.append('visitor_id', visitorId);
        navigator.sendBeacon(tracker.dataset.leaveUrl, data);
        registered = false;
    };

    const start = () => {
        window.clearInterval(heartbeatTimer);
        heartbeat();
        heartbeatTimer = window.setInterval(heartbeat, 25000);
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') heartbeat();
    });
    window.addEventListener('pageshow', start);
    window.addEventListener('pagehide', leave);
    start();
}

function readStorage(storage, key) {
    try {
        return storage.getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(storage, key, value) {
    try {
        storage.setItem(key, value);
    } catch {
        // The in-memory value still tracks the current page when browser storage is unavailable.
    }
}

function createUuid() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();

    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map(value => value.toString(16).padStart(2, '0'));

    return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10).join('')}`;
}
