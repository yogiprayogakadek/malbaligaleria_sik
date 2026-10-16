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
    };

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
