function csvCell(value) {
    let text = String(value || '').replace(/\s+/g, ' ').trim();
    if (/^[=+\-@]/.test(text)) text = `'${text}`;

    return `"${text.replaceAll('"', '""')}"`;
}

function initializeStaffDataTable(root, index) {
    if (root.dataset.staffTableReady === 'true') return;
    root.dataset.staffTableReady = 'true';

    const table = root.querySelector('table');
    const search = root.querySelector('[data-staff-search]');
    const counter = root.querySelector('[data-staff-record-count]');
    const filteredEmpty = root.querySelector('[data-staff-filter-empty]');
    const stateKey = `staff-table:${window.location.pathname}:${root.id || index}`;
    let state = {};

    try {
        state = JSON.parse(window.sessionStorage.getItem(stateKey) || '{}');
    } catch {
        state = {};
    }

    const saveState = changes => {
        state = { ...state, ...changes };
        try {
            window.sessionStorage.setItem(stateKey, JSON.stringify(state));
        } catch {}
    };

    const setLoading = message => {
        root.classList.add('is-loading');
        root.setAttribute('aria-busy', 'true');
        let status = root.querySelector('[data-staff-loading]');
        if (!status) {
            status = document.createElement('div');
            status.className = 'dt-loading-state';
            status.dataset.staffLoading = '';
            status.setAttribute('role', 'status');
            status.innerHTML = '<span aria-hidden="true"></span><strong></strong>';
            root.appendChild(status);
        }
        status.querySelector('strong').textContent = message;
    };

    const filter = () => {
        const query = search?.value.trim().toLowerCase() || '';
        let visible = 0;

        root.querySelectorAll('[data-staff-record]').forEach(record => {
            const matches = query === '' || (record.dataset.search || '').includes(query);
            record.hidden = !matches;
            if (matches && record.matches('tr')) visible += 1;
        });

        if (counter) counter.textContent = String(query ? visible : root.dataset.totalRecords || visible);
        if (filteredEmpty) filteredEmpty.hidden = query === '' || visible > 0;
        saveState({ search: search?.value || '' });
    };

    if (search && typeof state.search === 'string') search.value = state.search;
    search?.addEventListener('input', filter);
    root.querySelector('[data-staff-per-page]')?.addEventListener('change', event => {
        setLoading('Memuat jumlah data yang dipilih...');
        saveState({ scrollY: window.scrollY });
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', event.target.value);
        url.searchParams.delete('page');
        window.location.assign(url);
    });
    root.querySelectorAll('[data-staff-density]').forEach(button => {
        button.addEventListener('click', () => {
            const compact = button.dataset.staffDensity === 'compact';
            table?.classList.toggle('dt-table--compact', compact);
            root.querySelectorAll('[data-staff-density]').forEach(item => item.classList.toggle('active', item === button));
            saveState({ density: button.dataset.staffDensity });
        });
    });
    root.querySelector('[data-staff-export]')?.addEventListener('click', () => {
        if (!table) return;

        const rows = [...table.querySelectorAll('tr')]
            .filter(row => !row.hidden)
            .map(row => [...row.querySelectorAll('th, td')].map(cell => csvCell(cell.innerText)).join(','));
        const blob = new Blob([`\uFEFF${rows.join('\r\n')}`], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `permohonan-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    });

    const density = state.density === 'compact' ? 'compact' : 'comfortable';
    root.querySelector(`[data-staff-density="${density}"]`)?.click();
    root.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || link.target === '_blank' || link.hasAttribute('download')) return;
        const target = new URL(link.href, window.location.href);
        if (target.origin !== window.location.origin) return;
        saveState({ scrollY: window.scrollY });
        setLoading('Membuka data...');
    });

    filter();
    if (typeof state.scrollY === 'number' && Number.isFinite(state.scrollY)) {
        requestAnimationFrame(() => window.scrollTo({ top: Number(state.scrollY), behavior: 'auto' }));
        saveState({ scrollY: null });
    }
}

window.initStaffDataTables = () => {
    document.querySelectorAll('[data-staff-data-table]').forEach(initializeStaffDataTable);
};

window.initStaffDataTables();
