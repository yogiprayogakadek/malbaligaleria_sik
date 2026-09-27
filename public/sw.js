'use strict';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));

self.addEventListener('push', event => {
    let payload = {};

    try {
        payload = event.data?.json() ?? {};
    } catch {
        payload = { body: 'Ada permohonan baru yang menunggu pemeriksaan.' };
    }

    const title = typeof payload.title === 'string' ? payload.title : 'Pemberitahuan MBG';
    const options = {
        body: typeof payload.body === 'string' ? payload.body : '',
        icon: payload.icon || '/pwa/icon-192.png',
        badge: payload.badge || '/pwa/badge-96.png',
        data: payload.data && typeof payload.data === 'object' ? payload.data : {},
        tag: typeof payload.tag === 'string' ? payload.tag : undefined,
        lang: payload.lang || 'id',
        renotify: Boolean(payload.renotify),
        vibrate: Array.isArray(payload.vibrate) ? payload.vibrate : undefined,
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();

    let targetUrl = new URL('/tr', self.location.origin);
    const requestedUrl = event.notification.data?.url;

    try {
        const candidate = new URL(requestedUrl, self.location.origin);
        const allowedPath = candidate.pathname === '/tr'
            || candidate.pathname.startsWith('/tr/')
            || candidate.pathname === '/admin'
            || candidate.pathname.startsWith('/admin/');

        if (candidate.origin === self.location.origin && allowedPath) {
            targetUrl = candidate;
        }
    } catch {
        // Keep the same-origin staff dashboard fallback.
    }

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const existing = windows.find(client => new URL(client.url).origin === self.location.origin);

        if (existing) {
            await existing.navigate(targetUrl.href);
            return existing.focus();
        }

        return self.clients.openWindow(targetUrl.href);
    })());
});
