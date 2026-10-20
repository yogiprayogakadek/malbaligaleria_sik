(() => {
    const root = document.querySelector('[data-desktop-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    if (!root || !toggle) {
        return;
    }

    const storageKey = 'mbg.desktop-sidebar-expanded';
    let expanded = false;

    try {
        expanded = window.localStorage.getItem(storageKey) === 'true';
    } catch {
        expanded = false;
    }

    const applyState = () => {
        root.classList.toggle('sidebar-expanded', expanded);
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? 'Tutup menu samping' : 'Buka menu samping');
        toggle.setAttribute('title', expanded ? 'Tutup menu' : 'Buka menu');
        if (expanded) hideTooltip();
    };

    const tooltip = document.createElement('div');
    tooltip.className = 'sidebar-floating-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.hidden = true;
    document.body.append(tooltip);

    function hideTooltip() {
        tooltip.hidden = true;
    }

    function showTooltip(item) {
        if (expanded || !window.matchMedia('(min-width: 768px)').matches) return;
        const label = item.dataset.sidebarTooltip || item.getAttribute('aria-label') || item.getAttribute('title') || item.querySelector('.nav-label')?.textContent?.trim();
        if (!label) return;

        const rect = item.getBoundingClientRect();
        tooltip.textContent = label;
        tooltip.hidden = false;
        tooltip.style.left = `${Math.round(rect.right + 12)}px`;
        tooltip.style.top = `${Math.round(rect.top + (rect.height / 2))}px`;
    }

    root.querySelectorAll('.desktop-sidebar .nav-item').forEach((item) => {
        if (!item.dataset.sidebarTooltip && item.title) {
            item.dataset.sidebarTooltip = item.title;
            item.removeAttribute('title');
        }
        item.addEventListener('pointerenter', () => showTooltip(item));
        item.addEventListener('pointerleave', hideTooltip);
        item.addEventListener('focus', () => showTooltip(item));
        item.addEventListener('blur', hideTooltip);
    });
    root.querySelector('.sidebar-nav')?.addEventListener('scroll', hideTooltip, { passive: true });
    window.addEventListener('resize', hideTooltip, { passive: true });

    toggle.addEventListener('click', () => {
        expanded = !expanded;
        applyState();

        try {
            window.localStorage.setItem(storageKey, String(expanded));
        } catch {
            // The sidebar still works when browser storage is unavailable.
        }
    });

    applyState();
})();
