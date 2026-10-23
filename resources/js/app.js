import './bootstrap';

document.addEventListener('submit', event => {
    const form = event.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

const permitSubmitProgress = document.getElementById('permitSubmitProgress');

window.showPermitSubmitProgress = (title = 'Mengirim permohonan') => {
    if (!permitSubmitProgress) return;

    const heading = document.getElementById('permitSubmitProgressTitle');
    if (heading) heading.textContent = title;
    permitSubmitProgress.hidden = false;
    document.body.classList.add('permit-submit-pending');
};

window.addEventListener('pageshow', event => {
    if (permitSubmitProgress) permitSubmitProgress.hidden = true;
    document.body.classList.remove('permit-submit-pending');

    if (!event.persisted) return;
    document.querySelectorAll('button.loading').forEach(button => {
        button.classList.remove('loading');
        button.disabled = false;
        const label = button.querySelector('.btn-label');
        if (label?.dataset.idleLabel) label.textContent = label.dataset.idleLabel;
    });
});

document.querySelectorAll('input[type="file"]').forEach(input => {
    const host = input.closest('.pf, .admin-form, form');
    const error = host?.querySelector('.pf-error, .admin-field-error, .permit-alert--error');
    const returnedWithError = (input.dataset.uploadError === 'true' || Boolean(error?.textContent?.trim()))
        && !input.files?.length;

    if (returnedWithError) {
        input.dataset.uploadRetry = 'true';
        input.addEventListener('click', () => {
            input.value = '';
        }, { once: true });
    }

    input.addEventListener('change', () => {
        if (!input.files?.length) return;
        input.setCustomValidity('');
        input.removeAttribute('aria-invalid');
        input.classList.remove('is-invalid');
        input.closest('.pf')?.classList.remove('pf--invalid');
        if (input.dataset.uploadRetry === 'true' && error) error.textContent = '';
        delete input.dataset.uploadRetry;
    });
});

import './push-notifications';
import './validator-form';
import './admin-validator-table';
import './staff-data-table';
import './loading-permit-dates';
import { getEcho } from './echo';

const realtimeRoot = document.querySelector('[data-validator-realtime]');
const accountRealtimeRoot = document.querySelector('[data-account-realtime]');
const baseDocumentTitle = document.title.replace(/^\(\d+\+?\)\s*/, '');
let notificationAudioContext = null;
let notificationSoundReady = false;

if (accountRealtimeRoot) {
    getEcho()
        .private(`validators.${accountRealtimeRoot.dataset.accountRealtime}`)
        .listen('.validator-account.deactivated', (event) => {
            window.showToast?.(event.message || 'Akun Anda telah dinonaktifkan.', 'error');

            window.setTimeout(() => {
                const logoutForm = document.querySelector('.staff-logout-form');
                if (logoutForm) {
                    logoutForm.requestSubmit();
                    return;
                }

                window.location.assign('/staff-access');
            }, 900);
        });
}

function updateTabNotificationCount(count) {
    const safeCount = Math.max(0, Number(count) || 0);
    const label = safeCount > 99 ? '99+' : String(safeCount);
    document.title = safeCount > 0 ? `(${label}) ${baseDocumentTitle}` : baseDocumentTitle;
}

function unlockNotificationSound() {
    if (notificationSoundReady) return;

    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;

    notificationAudioContext = notificationAudioContext || new AudioContext();
    notificationAudioContext.resume().then(() => {
        notificationSoundReady = true;
    }).catch(() => {});
}

function playNotificationSound() {
    if (!notificationSoundReady || !notificationAudioContext) return;

    const start = notificationAudioContext.currentTime + 0.02;
    [587.33, 739.99, 880].forEach((frequency, index) => {
        const oscillator = notificationAudioContext.createOscillator();
        const gain = notificationAudioContext.createGain();
        const noteStart = start + (index * 0.095);
        const noteEnd = noteStart + 0.16;

        oscillator.type = index === 1 ? 'triangle' : 'sine';
        oscillator.frequency.setValueAtTime(frequency, noteStart);
        gain.gain.setValueAtTime(0.0001, noteStart);
        gain.gain.exponentialRampToValueAtTime(0.065, noteStart + 0.018);
        gain.gain.exponentialRampToValueAtTime(0.0001, noteEnd);
        oscillator.connect(gain);
        gain.connect(notificationAudioContext.destination);
        oscillator.start(noteStart);
        oscillator.stop(noteEnd);
    });
}

if (realtimeRoot) {
    updateTabNotificationCount(realtimeRoot.dataset.unreadCount);
    ['pointerdown', 'keydown', 'touchstart'].forEach((eventName) => {
        window.addEventListener(eventName, unlockNotificationSound, { once: true, passive: true });
    });

    const userId = realtimeRoot.dataset.validatorRealtime;

    const userChannel = getEcho().private(`validators.${userId}`);

    userChannel
        .listen('.loading-permit.submitted', (event) => {
            handleStaffNotification(event);
        })
        .listen('.work-permit.submitted', (event) => {
            handleStaffNotification(event);
        })
        .listen('.work-permit.workflow-updated', (event) => {
            handleStaffNotification(event);
        });

    window.setInterval(syncNotificationFeed, 15000);
    window.addEventListener('focus', syncNotificationFeed);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') syncNotificationFeed();
    });
}

let notificationFeedSyncing = false;

function handleStaffNotification(event, playSound = true) {
    if (!realtimeRoot || !event.notification) return;

    const notificationId = Number(event.notification.id || 0);
    const latestId = Number(realtimeRoot.dataset.latestNotificationId || 0);
    if (notificationId && notificationId <= latestId) return;
    if (notificationId) realtimeRoot.dataset.latestNotificationId = String(notificationId);

    const isCurrentQueue = !event.category
        || realtimeRoot.dataset.pendingCategory === 'all'
        || event.category === realtimeRoot.dataset.pendingCategory;
    if (isCurrentQueue) updatePendingCount(Number(event.pending_count || 0));
    const unreadCount = Number(realtimeRoot.dataset.unreadCount || 0) + 1;
    realtimeRoot.dataset.unreadCount = String(unreadCount);
    updateTabNotificationCount(unreadCount);

    const notificationBadge = document.querySelector('[data-notification-badge]');
    if (notificationBadge) notificationBadge.hidden = false;

    const notificationCount = document.querySelector('[data-notification-count]');
    if (notificationCount) {
        notificationCount.textContent = `${unreadCount} baru`;
        notificationCount.hidden = false;
    }

    const clearNotifications = document.querySelector('[data-notification-clear]');
    if (clearNotifications) clearNotifications.hidden = false;

    prependNotification(event.notification);
    window.showToast?.(`${event.notification.title}: ${event.notification.body}`, 'info');
    if (playSound) playNotificationSound();
    if (isCurrentQueue) refreshValidatorQueue();
}

function updatePendingCount(pendingCount) {
    document.querySelectorAll('[data-pending-count]').forEach((element) => {
        element.textContent = pendingCount > 99 ? '99+' : String(pendingCount);
        element.hidden = pendingCount === 0;
    });

    const tooltip = document.querySelector('[data-pending-tooltip]');
    if (tooltip) tooltip.textContent = `Antrean (${pendingCount})`;
}

async function syncNotificationFeed() {
    const feedUrl = realtimeRoot?.dataset.notificationFeedUrl;
    if (!feedUrl || notificationFeedSyncing) return;

    notificationFeedSyncing = true;

    try {
        const url = new URL(feedUrl, window.location.origin);
        url.searchParams.set('after_id', realtimeRoot.dataset.latestNotificationId || '0');
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (response.redirected && new URL(response.url).pathname === '/staff-access') {
            window.location.assign(response.url);
            return;
        }
        if (!response.ok) return;

        const result = await response.json();
        updatePendingCount(Number(result.pending_count || 0));
        result.notifications.forEach((notification) => {
            handleStaffNotification({
                notification,
                category: notification.category,
                pending_count: result.pending_count,
            }, false);
        });
        if (result.notifications.length > 0) playNotificationSound();
        realtimeRoot.dataset.latestNotificationId = String(result.latest_id || realtimeRoot.dataset.latestNotificationId || 0);
    } catch (error) {
        console.warn('Sinkronisasi notifikasi staf belum tersedia.', error);
    } finally {
        notificationFeedSyncing = false;
    }
}

async function refreshValidatorQueue() {
    const currentResults = document.getElementById('validatorPermitResults');
    const url = new URL(window.location.href);
    const isFirstPage = !url.searchParams.has('page') || url.searchParams.get('page') === '1';
    const liveQueueStatuses = new Set(['pending', 'action', 'payment_review', 'tr_review', 'all', 'loading', 'work', 'today']);

    if (!currentResults || !liveQueueStatuses.has(currentResults.dataset.validatorQueueStatus) || !isFirstPage) {
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
        window.initSecretaryTable?.();
        initializeWorkPermitSearch();
        window.initStaffDataTables?.();

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

function initializeWorkPermitSearch() {
    const input = document.getElementById('workPermitSearch');
    if (!input || input.dataset.searchReady === 'true') return;

    input.dataset.searchReady = 'true';
    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-search]').forEach((item) => {
            item.hidden = query !== '' && !item.dataset.search.includes(query);
        });
    });
}

initializeWorkPermitSearch();

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

function initializeQueueReminderSelection() {
    const toggle = document.querySelector('[data-queue-select-all]');
    const items = [...document.querySelectorAll('[data-queue-item]')];
    if (!toggle || items.length === 0 || toggle.dataset.ready === 'true') return;

    toggle.dataset.ready = 'true';
    toggle.addEventListener('change', () => {
        items.forEach((item) => {
            item.checked = toggle.checked;
        });
    });
    items.forEach((item) => item.addEventListener('change', () => {
        toggle.checked = items.every((candidate) => candidate.checked);
        toggle.indeterminate = !toggle.checked && items.some((candidate) => candidate.checked);
    }));
}

initializeQueueReminderSelection();

function initializeEmergencyValidationForms() {
    document.querySelectorAll('[data-emergency-form]').forEach((form) => {
        const decision = form.querySelector('[data-emergency-decision]');
        const deposit = form.querySelector('[data-emergency-deposit]');
        const amount = deposit?.querySelector('input');
        if (!decision || !deposit || decision.dataset.ready === 'true') return;

        const sync = () => {
            const visible = decision.value === 'require_deposit';
            deposit.hidden = !visible;
            if (amount) amount.required = visible;
        };
        decision.dataset.ready = 'true';
        decision.addEventListener('change', sync);
        sync();
    });
}

initializeEmergencyValidationForms();

function initializeDataMaintenanceSelection() {
    const toggle = document.querySelector('[data-maintenance-select-all]');
    const items = [...document.querySelectorAll('[data-maintenance-item]')];
    if (!toggle || items.length === 0 || toggle.dataset.ready === 'true') return;

    toggle.dataset.ready = 'true';
    toggle.addEventListener('change', () => items.forEach((item) => {
        item.checked = toggle.checked;
    }));
    items.forEach((item) => item.addEventListener('change', () => {
        toggle.checked = items.every((candidate) => candidate.checked);
        toggle.indeterminate = !toggle.checked && items.some((candidate) => candidate.checked);
    }));
}

initializeDataMaintenanceSelection();
