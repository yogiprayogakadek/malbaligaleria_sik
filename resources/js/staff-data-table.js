function csvCell(value) {
    let text = String(value || '').replace(/\s+/g, ' ').trim();
    if (/^[=+\-@]/.test(text)) text = `'${text}`;

    return `"${text.replaceAll('"', '""')}"`;
}

function initializeStaffDataTable(root) {
    if (root.dataset.staffTableReady === 'true') return;
    root.dataset.staffTableReady = 'true';

    const table = root.querySelector('table');
    const search = root.querySelector('[data-staff-search]');
    const counter = root.querySelector('[data-staff-record-count]');
    const filteredEmpty = root.querySelector('[data-staff-filter-empty]');

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
    };

    search?.addEventListener('input', filter);
    root.querySelector('[data-staff-per-page]')?.addEventListener('change', event => {
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

    filter();
}

window.initStaffDataTables = () => {
    document.querySelectorAll('[data-staff-data-table]').forEach(initializeStaffDataTable);
};

window.initStaffDataTables();
