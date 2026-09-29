@extends('layouts.validator')

@section('title', 'Monitoring Permohonan : Secretary : Mal Bali Galeria')
@section('page-title', 'Dashboard Secretary')

@php
  $avatarColors = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#db2777', '#0891b2', '#4f46e5', '#ea580c'];
  $initials = static function (string $name): string {
    $words = preg_split('/\s+/', trim($name));
    return strtoupper(collect($words)->take(2)->map(fn ($word) => substr($word, 0, 1))->implode('')) ?: 'MB';
  };
@endphp

@section('content')
<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><span>Secretary</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Monitoring Permohonan</span></div>
      <h1 class="page-title">Permohonan tenant</h1>
      <p class="page-subtitle">Pantau pengajuan loading, unloading, dan izin kerja. Akses ini hanya untuk melihat data.</p>
    </div>
  </div>

  @if(session('success'))
    <div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>
  @endif

  <div class="dt-card" id="validatorPermitResults" data-validator-queue-status="{{ $status }}" aria-live="polite">
    <div class="dt-toolbar">
      <div class="dt-header-left">
        <h2 class="dt-title">Daftar Permohonan</h2>
        <span class="dt-count">&middot; <span id="dtRecordsCount">{{ $permits->total() }}</span> data</span>
      </div>

      <div class="dt-header-right">
        <div class="dt-perpage-wrap">
          <label for="dtPerPage" class="dt-perpage-label">Tampilkan</label>
          <select id="dtPerPage" class="dt-select dt-perpage-select" aria-label="Jumlah data per halaman">
            @foreach([5, 10, 20, 50, 100, 'all'] as $option)
              <option value="{{ $option }}" {{ (string) $perPageRaw === (string) $option ? 'selected' : '' }}>{{ $option === 'all' ? 'Semua' : $option }}</option>
            @endforeach
          </select>
          <span class="dt-perpage-label">data</span>
        </div>

        <div class="dt-search-wrap">
          <svg><use href="#i-search"/></svg>
          <input type="search" id="dtSearchInput" class="dt-search-input" placeholder="Cari tenant / nomor surat..." aria-label="Cari permohonan">
        </div>

        <select id="dtFilterStatus" class="dt-select" aria-label="Filter status permohonan">
          <option value="all" {{ $permitStatus === 'all' ? 'selected' : '' }}>Semua status</option>
          @foreach($statusGroups as $groupLabel => $options)
            <optgroup label="{{ $groupLabel }}">
              @foreach($options as $value => $label)
                <option value="{{ $value }}" {{ $permitStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>

        <div class="dt-density-group" role="group" aria-label="Kerapatan tabel">
          <button type="button" class="dt-density-btn active" id="btnDensityComfortable">Normal</button>
          <button type="button" class="dt-density-btn" id="btnDensityCompact">Ringkas</button>
        </div>

        <button type="button" class="dt-btn-export" id="dtExportBtn" title="Unduh CSV">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
          <span>Unduh CSV</span>
        </button>
      </div>
    </div>

    <div class="dt-table-responsive">
      <table class="dt-table" id="dtTable">
        <thead><tr><th>TENANT / PEMOHON</th><th>NOMOR SURAT</th><th>JENIS</th><th>STATUS</th><th>PERIODE</th><th>DIVISI</th><th>TINDAKAN</th></tr></thead>
        <tbody>
          @forelse($permits as $permit)
            @php $avatarColor = $avatarColors[abs(crc32($permit->entity_name)) % count($avatarColors)]; @endphp
            <tr class="dt-row status-{{ $permit->status }}" data-permit-row data-search="{{ strtolower($permit->entity_name.' '.$permit->applicant_name.' '.$permit->permit_number.' '.$permit->type_label.' '.$permit->subtype_label) }}">
              <td><div class="dt-user-cell"><div class="dt-avatar" style="background-color: {{ $avatarColor }}">{{ $initials($permit->entity_name) }}</div><div class="dt-user-info"><span class="dt-user-name">{{ $permit->entity_name }}</span><span class="dt-user-sub">{{ $permit->applicant_name }}</span></div></div></td>
              <td><span class="dt-mono-ref">{{ $permit->permit_number }}</span><div class="dt-user-sub">{{ $permit->created_at->format('d M Y, H:i') }}</div></td>
              <td><span class="dt-badge-role role-{{ $permit->permit_type }}">{{ $permit->type_label }}</span><div class="dt-user-sub">{{ $permit->subtype_label }}</div></td>
              <td><span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span></td>
              <td><span class="dt-period">{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</span><div class="dt-user-sub">{{ $permit->detail_label }}</div></td>
              <td><span class="dt-badge-role">{{ $permit->division ?: 'TR' }}</span></td>
              <td><a href="{{ $permit->view_url }}" class="btn-dt-action btn-dt-action--view">Lihat</a></td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="dt-empty-state"><span class="dt-empty-icon"><svg><use href="#i-file"/></svg></span><h3>Belum ada permohonan</h3><p>Data baru akan muncul otomatis saat tenant mengirim permohonan.</p></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="dt-mobile-list">
      @forelse($permits as $permit)
        <article class="dt-mobile-card" data-permit-row data-search="{{ strtolower($permit->entity_name.' '.$permit->applicant_name.' '.$permit->permit_number.' '.$permit->type_label.' '.$permit->subtype_label) }}">
          <div class="dt-mobile-card-header"><div class="dt-mobile-card-main"><div class="dt-mobile-card-title-row"><span class="dt-mobile-card-title">{{ $permit->entity_name }}</span><span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span></div><div class="dt-mobile-card-meta"><span class="dt-mono-ref">{{ $permit->permit_number }}</span><span>{{ $permit->type_label }}</span></div></div></div>
          <div class="dt-mobile-detail-grid"><div class="dt-mobile-detail-row"><dt>Pemohon</dt><dd>{{ $permit->applicant_name }}</dd></div><div class="dt-mobile-detail-row"><dt>Kategori</dt><dd>{{ $permit->subtype_label }}</dd></div><div class="dt-mobile-detail-row"><dt>Periode</dt><dd>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd></div><div class="dt-mobile-detail-row"><dt>Divisi</dt><dd>{{ $permit->division ?: 'TR' }}</dd></div></div>
          <div class="dt-mobile-card-footer"><a href="{{ $permit->view_url }}" class="btn-dt-action btn-dt-action--view">Lihat Permohonan</a></div>
        </article>
      @empty
        <div class="dt-empty-state dt-empty-state--mobile"><span class="dt-empty-icon"><svg><use href="#i-file"/></svg></span><h3>Belum ada permohonan</h3><p>Data baru akan muncul otomatis saat tenant mengirim permohonan.</p></div>
      @endforelse
    </div>

    @if($permits->total() > 0)
      <div class="dt-footer">
        <div class="dt-footer-info">Menampilkan {{ $permits->firstItem() }} sampai {{ $permits->lastItem() }} dari {{ $permits->total() }} data</div>
        <div class="dt-pagination">
          @if($permits->onFirstPage())<span class="dt-page-btn disabled" aria-disabled="true">Prev</span>@else<a href="{{ $permits->previousPageUrl() }}" class="dt-page-btn" rel="prev">Prev</a>@endif
          @foreach($permits->getUrlRange(max(1, $permits->currentPage() - 2), min($permits->lastPage(), $permits->currentPage() + 2)) as $page => $url)
            @if($page === $permits->currentPage())<span class="dt-page-btn active" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" class="dt-page-btn">{{ $page }}</a>@endif
          @endforeach
          @if($permits->hasMorePages())<a href="{{ $permits->nextPageUrl() }}" class="dt-page-btn" rel="next">Next</a>@else<span class="dt-page-btn disabled" aria-disabled="true">Next</span>@endif
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
window.initSecretaryTable = function () {
  const search = document.getElementById('dtSearchInput');
  const table = document.getElementById('dtTable');
  const normal = document.getElementById('btnDensityComfortable');
  const compact = document.getElementById('btnDensityCompact');

  search?.addEventListener('input', () => {
    const query = search.value.trim().toLowerCase();
    document.querySelectorAll('[data-permit-row]').forEach(row => row.hidden = query !== '' && !row.dataset.search.includes(query));
  });
  document.getElementById('dtPerPage')?.addEventListener('change', event => {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', event.target.value);
    url.searchParams.delete('page');
    window.location.assign(url);
  });
  document.getElementById('dtFilterStatus')?.addEventListener('change', async event => {
    const currentResults = document.getElementById('validatorPermitResults');
    const url = new URL(window.location.href);
    if (event.target.value === 'all') url.searchParams.delete('permit_status');
    else url.searchParams.set('permit_status', event.target.value);
    url.searchParams.delete('page');
    currentResults?.classList.add('dt-card--loading');
    event.target.disabled = true;

    try {
      const response = await fetch(url, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (!response.ok) throw new Error('Filter status tidak dapat dimuat.');
      const html = await response.text();
      const nextDocument = new DOMParser().parseFromString(html, 'text/html');
      const nextResults = nextDocument.getElementById('validatorPermitResults');
      if (!nextResults) throw new Error('Data tabel tidak ditemukan.');
      currentResults.replaceWith(nextResults);
      history.replaceState({}, '', url);
      window.initSecretaryTable();
      nextResults.classList.add('validator-results-updated');
      window.setTimeout(() => nextResults.classList.remove('validator-results-updated'), 900);
    } catch (error) {
      currentResults?.classList.remove('dt-card--loading');
      event.target.disabled = false;
      window.showToast?.(error.message, 'error');
    }
  });
  normal?.addEventListener('click', () => { table?.classList.remove('dt-table--compact'); normal.classList.add('active'); compact?.classList.remove('active'); });
  compact?.addEventListener('click', () => { table?.classList.add('dt-table--compact'); compact.classList.add('active'); normal?.classList.remove('active'); });
  document.getElementById('dtExportBtn')?.addEventListener('click', () => {
    const rows = [['Tenant / Pemohon', 'Nomor Surat', 'Jenis', 'Status', 'Periode', 'Divisi']];
    document.querySelectorAll('#dtTable tbody tr[data-permit-row]:not([hidden])').forEach(row => rows.push(Array.from(row.cells).slice(0, 6).map(cell => cell.innerText.replace(/\s+/g, ' ').trim())));
    if (rows.length === 1) return;
    const csv = rows.map(row => row.map(value => `"${value.replace(/"/g, '""')}"`).join(',')).join('\n');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    link.download = 'permohonan-secretary.csv';
    link.click();
    URL.revokeObjectURL(link.href);
  });
};
window.initSecretaryTable();
</script>
@endpush
