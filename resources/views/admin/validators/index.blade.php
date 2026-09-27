@extends('layouts.admin')

@section('title', 'Data Validator : Admin MBG')
@section('page-title', 'Akun Validator')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Validator</span></div>
      <h1 class="page-title">Data validator</h1>
      <p class="page-subtitle">Kelola akun staf per divisi tanpa menghapus riwayat aktivitas.</p>
    </div>
    <a href="{{ route('admin.validators.create') }}" class="admin-primary-button"><svg><use href="#i-plus"/></svg>Tambah validator</a>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif

  <div class="dt-card admin-validator-datatable" data-admin-validator-table>
    <div class="dt-toolbar">
      <div class="dt-header-left">
        <h2 class="dt-title">Daftar akun</h2>
        <span class="dt-count">· <span id="validatorRecordsCount">{{ $validators->count() }}</span> data pada halaman ini</span>
      </div>
      <div class="dt-header-right">
        <div class="dt-perpage-wrap">
          <label for="validatorPerPage" class="dt-perpage-label">Tampilkan</label>
          <select id="validatorPerPage" class="dt-select dt-perpage-select" aria-label="Jumlah validator per halaman">
            @foreach([5, 10, 20, 50, 100, 'all'] as $option)
              <option value="{{ $option }}" {{ (string) $perPageRaw === (string) $option ? 'selected' : '' }}>{{ $option === 'all' ? 'Semua' : $option }}</option>
            @endforeach
          </select>
          <span class="dt-perpage-label">data</span>
        </div>
        <div class="dt-search-wrap">
          <svg><use href="#i-search"/></svg>
          <input type="search" id="validatorSearch" class="dt-search-input" placeholder="Cari nama, email, telepon..." aria-label="Cari validator">
        </div>
        <select id="validatorDivisionFilter" class="dt-select" aria-label="Filter divisi">
          <option value="">Semua divisi</option>
          @foreach(config('divisions') as $code => $label)<option value="{{ $code }}">{{ $code }}</option>@endforeach
        </select>
        <select id="validatorStatusFilter" class="dt-select" aria-label="Filter status akun">
          <option value="">Semua status</option>
          <option value="active">Aktif</option>
          <option value="inactive">Nonaktif</option>
        </select>
        <div class="dt-density-group" role="group" aria-label="Kerapatan tabel">
          <button type="button" class="dt-density-btn active" id="validatorDensityNormal">Normal</button>
          <button type="button" class="dt-density-btn" id="validatorDensityCompact">Ringkas</button>
        </div>
        <button type="button" class="dt-btn-export" id="validatorExport" title="Unduh data validator sebagai CSV">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
          <span>Unduh CSV</span>
        </button>
      </div>
    </div>

    @if($validators->isEmpty())
      <div class="tr-empty"><svg width="40" height="40"><use href="#i-user"/></svg><p>Belum ada akun validator.</p></div>
    @else
      <div class="dt-table-responsive">
        <table class="dt-table" id="validatorDataTable">
          <thead><tr>
            <th data-sort="name">VALIDATOR <span class="dt-sort-icon">⇅</span></th>
            <th data-sort="division">DIVISI <span class="dt-sort-icon">⇅</span></th>
            <th>KONTAK</th>
            <th data-sort="status">STATUS <span class="dt-sort-icon">⇅</span></th>
            <th data-sort="date">DIPERBARUI <span class="dt-sort-icon">⇅</span></th>
            <th>TINDAKAN</th>
          </tr></thead>
          <tbody>
            @foreach($validators as $validator)
              <tr class="dt-row" data-validator-record
                  data-name="{{ strtolower($validator->name) }}"
                  data-email="{{ strtolower($validator->email) }}"
                  data-phone="{{ strtolower($validator->phone) }}"
                  data-display-name="{{ $validator->name }}"
                  data-display-email="{{ $validator->email }}"
                  data-display-phone="{{ $validator->phone }}"
                  data-division="{{ $validator->division }}"
                  data-status="{{ $validator->is_active ? 'active' : 'inactive' }}"
                  data-date="{{ $validator->updated_at->format('Y-m-d H:i:s') }}">
                <td><div class="dt-user-cell"><span class="dt-avatar admin-validator-dt-avatar">{{ strtoupper(substr($validator->name, 0, 1)) }}</span><span class="dt-user-info"><strong class="dt-user-name">{{ $validator->name }}</strong><small class="dt-user-sub">ID akun #{{ $validator->id }}</small></span></div></td>
                <td><span class="admin-division-badge">{{ $validator->division }}</span><small class="dt-user-sub">{{ config("divisions.{$validator->division}") }}</small></td>
                <td><strong class="admin-contact-value">{{ $validator->email }}</strong><small class="dt-user-sub">{{ $validator->phone }}</small></td>
                <td><span class="dt-badge-status {{ $validator->is_active ? 'dt-status-approved' : 'dt-status-rejected' }}"><span class="dt-dot"></span>{{ $validator->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td><strong class="admin-contact-value">{{ $validator->updated_at->format('d M Y') }}</strong><small class="dt-user-sub">{{ $validator->updated_at->format('H:i') }} WITA</small></td>
                <td>
                  <div class="admin-validator-actions">
                    <a href="{{ route('admin.validators.edit', $validator) }}" class="admin-secondary-button admin-edit-button"><svg><use href="#i-edit"/></svg>Edit</a>
                    <form action="{{ route('admin.validators.status', $validator) }}" method="POST" @if($validator->is_active) data-confirm-deactivate="{{ $validator->name }}" @endif>
                      @csrf @method('PATCH')
                      <input type="hidden" name="is_active" value="{{ $validator->is_active ? 0 : 1 }}">
                      <button type="submit" class="admin-status-button {{ $validator->is_active ? 'danger' : '' }}">{{ $validator->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="dt-mobile-list">
        @foreach($validators as $validator)
          <article class="dt-mobile-card" data-validator-record
                   data-name="{{ strtolower($validator->name) }}" data-email="{{ strtolower($validator->email) }}" data-phone="{{ strtolower($validator->phone) }}"
                   data-division="{{ $validator->division }}" data-status="{{ $validator->is_active ? 'active' : 'inactive' }}">
            <div class="dt-mobile-card-header">
              <span class="dt-avatar admin-validator-dt-avatar">{{ strtoupper(substr($validator->name, 0, 1)) }}</span>
              <div class="dt-mobile-card-main">
                <div class="dt-mobile-card-title-row"><strong class="dt-mobile-card-title">{{ $validator->name }}</strong><span class="dt-badge-status {{ $validator->is_active ? 'dt-status-approved' : 'dt-status-rejected' }}"><span class="dt-dot"></span>{{ $validator->is_active ? 'Aktif' : 'Nonaktif' }}</span></div>
                <div class="dt-mobile-card-meta"><span class="admin-division-badge">{{ $validator->division }}</span><span>{{ $validator->email }}</span></div>
              </div>
            </div>
            <div class="admin-validator-mobile-contact"><span>{{ $validator->phone }}</span><span>Diperbarui {{ $validator->updated_at->format('d M Y, H:i') }}</span></div>
            <div class="dt-mobile-card-footer admin-validator-mobile-actions">
              <a href="{{ route('admin.validators.edit', $validator) }}" class="admin-secondary-button"><svg><use href="#i-edit"/></svg>Edit</a>
              <form action="{{ route('admin.validators.status', $validator) }}" method="POST" @if($validator->is_active) data-confirm-deactivate="{{ $validator->name }}" @endif>
                @csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $validator->is_active ? 0 : 1 }}">
                <button type="submit" class="admin-status-button {{ $validator->is_active ? 'danger' : '' }}">{{ $validator->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
              </form>
            </div>
          </article>
        @endforeach
      </div>

      <div class="tr-empty admin-filter-empty" id="validatorFilterEmpty" hidden><p>Tidak ada validator yang sesuai dengan pencarian atau filter.</p></div>
      <div class="dt-footer">
        <div class="dt-footer-info">Menampilkan <strong>{{ $validators->firstItem() ?? 0 }}</strong>–<strong>{{ $validators->lastItem() ?? 0 }}</strong> dari <strong>{{ $validators->total() }}</strong> validator</div>
        <div class="dt-pagination-nav" role="navigation" aria-label="Navigasi halaman validator">
          @if($validators->onFirstPage())<span class="dt-page-btn disabled">‹ Prev</span>@else<a href="{{ $validators->previousPageUrl() }}" class="dt-page-btn">‹ Prev</a>@endif
          @foreach($validators->getUrlRange(max(1, $validators->currentPage() - 2), min($validators->lastPage(), $validators->currentPage() + 2)) as $page => $url)
            @if($page === $validators->currentPage())<span class="dt-page-btn active" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" class="dt-page-btn">{{ $page }}</a>@endif
          @endforeach
          @if($validators->hasMorePages())<a href="{{ $validators->nextPageUrl() }}" class="dt-page-btn">Next ›</a>@else<span class="dt-page-btn disabled">Next ›</span>@endif
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('[data-confirm-deactivate]').forEach(form => {
    form.addEventListener('submit', event => {
      if (!window.confirm(`Nonaktifkan ${form.dataset.confirmDeactivate}? Akun akan langsung dikeluarkan dari semua halaman yang sedang terbuka.`)) event.preventDefault();
    });
  });
</script>
@endpush
