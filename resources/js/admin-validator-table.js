const tableRoot = document.querySelector('[data-admin-validator-table]');

if (tableRoot) {
    const search = document.getElementById('validatorSearch');
    const division = document.getElementById('validatorDivisionFilter');
    const status = document.getElementById('validatorStatusFilter');
    const count = document.getElementById('validatorRecordsCount');
    const empty = document.getElementById('validatorFilterEmpty');
    const table = document.getElementById('validatorDataTable');

    const applyFilters = () => {
        const query = search?.value.trim().toLowerCase() || '';
        const divisionValue = division?.value || '';
        const statusValue = status?.value || '';
        let visible = 0;

        tableRoot.querySelectorAll('[data-validator-record]').forEach((record) => {
            const text = `${record.dataset.name} ${record.dataset.email} ${record.dataset.phone}`;
            const matches = (!query || text.includes(query))
                && (!divisionValue || record.dataset.division === divisionValue)
                && (!statusValue || record.dataset.status === statusValue);

            record.hidden = !matches;
            if (matches && record.matches('tr')) visible += 1;
        });

        if (count) count.textContent = String(visible);
        if (empty) empty.hidden = visible !== 0;
    };

    search?.addEventListener('input', applyFilters);
    division?.addEventListener('change', applyFilters);
    status?.addEventListener('change', applyFilters);

    document.getElementById('validatorPerPage')?.addEventListener('change', (event) => {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', event.target.value);
        url.searchParams.delete('page');
        window.location.assign(url.toString());
    });

    document.getElementById('validatorDensityNormal')?.addEventListener('click', (event) => {
        event.currentTarget.classList.add('active');
        document.getElementById('validatorDensityCompact')?.classList.remove('active');
        table?.classList.remove('dt-table--compact');
    });

    document.getElementById('validatorDensityCompact')?.addEventListener('click', (event) => {
        event.currentTarget.classList.add('active');
        document.getElementById('validatorDensityNormal')?.classList.remove('active');
        table?.classList.add('dt-table--compact');
    });

    const sortDirections = new Map();
    tableRoot.querySelectorAll('th[data-sort]').forEach((heading) => {
        heading.addEventListener('click', () => {
            const key = heading.dataset.sort;
            const ascending = !sortDirections.get(key);
            const body = table?.tBodies[0];
            if (!body) return;

            [...body.querySelectorAll('tr[data-validator-record]')]
                .sort((left, right) => {
                    const result = (left.dataset[key] || '').localeCompare(right.dataset[key] || '', 'id');
                    return ascending ? result : -result;
                })
                .forEach((row) => body.appendChild(row));

            sortDirections.set(key, ascending);
            tableRoot.querySelectorAll('.dt-sort-icon').forEach((icon) => { icon.textContent = '⇅'; });
            const icon = heading.querySelector('.dt-sort-icon');
            if (icon) icon.textContent = ascending ? '▲' : '▼';
        });
    });

    document.getElementById('validatorExport')?.addEventListener('click', () => {
        const rows = [...tableRoot.querySelectorAll('tr[data-validator-record]:not([hidden])')];
        if (!rows.length) return;

        const lines = [['Nama', 'Email', 'Telepon', 'Divisi', 'Status']];
        rows.forEach((row) => lines.push([
            row.dataset.displayName,
            row.dataset.displayEmail,
            row.dataset.displayPhone,
            row.dataset.division,
            row.dataset.status === 'active' ? 'Aktif' : 'Nonaktif',
        ]));

        const csv = lines.map((line) => line.map(csvValue).join(',')).join('\n');
        const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `data-validator-${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    });
}

function csvValue(value) {
    return `"${String(value || '').replaceAll('"', '""')}"`;
}
