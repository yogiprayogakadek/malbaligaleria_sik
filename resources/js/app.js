import './bootstrap';
import './push-notifications';
import { getEcho } from './echo';

const realtimeRoot = document.querySelector('[data-validator-realtime]');

if (realtimeRoot) {
    const userId = realtimeRoot.dataset.validatorRealtime;

    getEcho()
        .private(`validators.${userId}`)
        .listen('.loading-permit.submitted', (event) => {
            const pendingCount = Number(event.pending_count || 0);
            const unreadCount = Number(realtimeRoot.dataset.unreadCount || 0) + 1;
            realtimeRoot.dataset.unreadCount = String(unreadCount);

            document.querySelectorAll('[data-pending-count]').forEach((element) => {
                element.textContent = pendingCount > 99 ? '99+' : String(pendingCount);
                element.hidden = pendingCount === 0;
            });

            const tooltip = document.querySelector('[data-pending-tooltip]');
            if (tooltip) tooltip.textContent = `Antrean (${pendingCount})`;

            const notificationBadge = document.querySelector('[data-notification-badge]');
            if (notificationBadge) notificationBadge.hidden = false;

            const notificationCount = document.querySelector('[data-notification-count]');
            if (notificationCount) {
                notificationCount.textContent = `${unreadCount} baru`;
                notificationCount.hidden = false;
            }

            prependNotification(event.notification);
            window.showToast?.(`${event.notification.title}: ${event.notification.body}`, 'info');
            refreshValidatorQueue();
        });
}

async function refreshValidatorQueue() {
    const currentResults = document.getElementById('validatorPermitResults');
    const url = new URL(window.location.href);
    const isFirstPage = !url.searchParams.has('page') || url.searchParams.get('page') === '1';

    if (!currentResults || currentResults.dataset.validatorQueueStatus !== 'pending' || !isFirstPage) {
        return;
    }

    const searchValue = document.getElementById('dtSearchInput')?.value || '';
    const directionValue = document.getElementById('dtFilterDirection')?.value || '';
    const compact = document.getElementById('dtTable')?.classList.contains('dt-table--compact') || false;

    try {
        const response = await fetch(url.toString(), {
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) return;

        const html = await response.text();
        const nextDocument = new DOMParser().parseFromString(html, 'text/html');
        const nextResults = nextDocument.getElementById('validatorPermitResults');
        if (!nextResults) return;

        currentResults.replaceWith(nextResults);
        window.initValidatorTable?.();

        const nextSearch = document.getElementById('dtSearchInput');
        const nextDirection = document.getElementById('dtFilterDirection');
        if (nextSearch) nextSearch.value = searchValue;
        if (nextDirection) nextDirection.value = directionValue;
        if (compact) document.getElementById('btnDensityCompact')?.click();

        nextSearch?.dispatchEvent(new Event('input'));
        nextResults.classList.add('validator-results-updated');
        window.setTimeout(() => nextResults.classList.remove('validator-results-updated'), 900);
    } catch (error) {
        console.warn('Antrean terbaru belum dapat dimuat.', error);
    }
}

function prependNotification(notification) {
    const list = document.getElementById('validatorNotifList');
    if (!list) return;

    document.getElementById('validatorNotifEmpty')?.remove();

    const form = document.createElement('form');
    form.action = notification.read_url;
    form.method = 'POST';
    form.className = 'validator-notif-form';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrf;

    const button = document.createElement('button');
    button.type = 'submit';
    button.className = 'notif-item unread';

    const icon = document.createElement('span');
    icon.className = 'notif-icon notif-icon--blue';
    icon.innerHTML = '<svg><use href="#i-clock"></use></svg>';

    const body = document.createElement('span');
    body.className = 'notif-body';
    body.append(
        notificationText('notif-title', notification.title),
        notificationText('notif-desc', notification.body),
        notificationText('notif-time', 'Baru saja'),
    );

    button.append(icon, body);
    form.append(token, button);
    list.prepend(form);

    while (list.children.length > 6) list.lastElementChild.remove();
}

function notificationText(className, value) {
    const element = document.createElement('span');
    element.className = className;
    element.textContent = value;
    return element;
}
