@extends('layouts.admin')

@section('title', 'Petugas Scanner : Admin MBG')
@section('page-title', 'Petugas Scanner')

@section('content')
<div class="page-wrap admin-page-wrap security-officer-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Sistem</span><svg><use href="#i-chevron-right"/></svg><span>Petugas scanner</span></div>
      <h1 class="page-title">Akses petugas security</h1>
      <p class="page-subtitle">Daftarkan petugas secara manual atau impor data penugasan dari Excel.</p>
    </div>
    <a href="{{ route('admin.security-officers.template') }}" class="admin-secondary-button"><svg><use href="#i-file"/></svg>Unduh template</a>
  </div>

  @if(session('success'))
    <div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>
  @endif
  @if(session('whatsapp_delivery'))
    <div class="permit-alert permit-alert--info" role="status"><svg><use href="#i-phone"/></svg><span>{{ session('whatsapp_delivery') }}</span></div>
  @endif
  @if($errors->any())
    <div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  @if(session('generated_officer'))
    @php($credential = session('generated_officer'))
    <section class="officer-pin-notice" aria-labelledby="generatedPinTitle">
      <div>
        <span class="officer-pin-eyebrow">PIN hanya ditampilkan sekarang</span>
        <h2 id="generatedPinTitle">{{ $credential['name'] }} · {{ $credential['officer_id'] }}</h2>
        <p>Berikan PIN kepada petugas melalui saluran yang aman. Sistem hanya menyimpan hasil hash dan tidak dapat menampilkannya kembali.</p>
      </div>
      <strong class="officer-pin-value" aria-label="PIN enam digit">{{ $credential['pin'] }}</strong>
    </section>
  @endif

  <div class="officer-entry-grid">
    <section class="admin-panel officer-entry-panel">
      <div class="admin-panel-header"><div><h2>Tambah satu petugas</h2><p>PIN enam digit dibuat otomatis dan dikirim ke WhatsApp petugas.</p></div></div>
      <form action="{{ route('admin.security-officers.store') }}" method="POST" class="admin-form officer-manual-form">
        @csrf
        <label>Nama petugas
          <input type="text" name="name" value="{{ old('name') }}" maxlength="120" required autocomplete="name">
        </label>
        <label>Nomor ID petugas
          <input type="text" name="officer_id" value="{{ old('officer_id') }}" maxlength="60" required autocomplete="off" placeholder="Contoh: SEC-001">
        </label>
        <label>Nomor WhatsApp
          <input type="tel" name="whatsapp" value="{{ old('whatsapp') }}" maxlength="30" required inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx">
        </label>
        <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-plus"/></svg>Tambah petugas</button></div>
      </form>
    </section>

    <section class="admin-panel officer-entry-panel">
      <div class="admin-panel-header"><div><h2>Impor dari Excel</h2><p>Maksimal 500 baris. Akun baru menerima akses melalui WhatsApp secara bertahap.</p></div></div>
      <form action="{{ route('admin.security-officers.import') }}" method="POST" enctype="multipart/form-data" class="admin-form officer-import-form">
        @csrf
        <label>File petugas
          <span class="officer-file-control">
            <input type="file" name="officer_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel" data-upload-error="{{ $errors->has('officer_file') ? 'true' : 'false' }}" required data-officer-file>
            <span data-officer-file-name>Pilih file Excel</span>
          </span>
          <small>Gunakan kolom Nama, Nomor ID Petugas, dan Nomor WhatsApp. Hasil impor beserta PIN baru akan diunduh sebagai Excel.</small>
        </label>
        <div class="officer-import-note"><svg><use href="#i-lock"/></svg><span>PIN hanya tersedia pada laporan hasil impor pertama. Simpan laporan secara terbatas lalu hapus setelah PIN dibagikan.</span></div>
        <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-file"/></svg>Periksa dan impor</button></div>
      </form>
    </section>
  </div>

  <nav class="admin-status-tabs" aria-label="Filter status petugas">
    @foreach(['active' => 'Aktif', 'inactive' => 'Nonaktif', 'all' => 'Semua'] as $key => $label)
      <a href="{{ route('admin.security-officers.index', ['status' => $key]) }}" class="{{ $status === $key ? 'active' : '' }}">{{ $label }}</a>
    @endforeach
  </nav>

  <section class="dt-card security-officer-datatable" data-security-officer-table>
    <div class="dt-toolbar">
      <div class="dt-header-left">
        <h2 class="dt-title">Daftar petugas</h2>
        <span class="dt-count">· <span id="officerRecordsCount">{{ $officers->count() }}</span> data pada halaman ini</span>
      </div>
      <div class="dt-header-right">
        <div class="dt-perpage-wrap">
          <label for="officerPerPage" class="dt-perpage-label">Tampilkan</label>
          <select id="officerPerPage" class="dt-select dt-perpage-select" aria-label="Jumlah petugas per halaman">
            @foreach([10, 20, 50, 100] as $option)<option value="{{ $option }}" @selected((string) $perPageRaw === (string) $option)>{{ $option }}</option>@endforeach
          </select>
          <span class="dt-perpage-label">data</span>
        </div>
        <div class="dt-search-wrap">
          <svg><use href="#i-search"/></svg>
          <input type="search" id="officerSearch" class="dt-search-input" placeholder="Cari nama, ID, atau WhatsApp" aria-label="Cari petugas security">
        </div>
        <div class="dt-density-group" role="group" aria-label="Kerapatan tabel">
          <button type="button" class="dt-density-btn active" id="officerDensityNormal">Normal</button>
          <button type="button" class="dt-density-btn" id="officerDensityCompact">Ringkas</button>
        </div>
      </div>
    </div>
    @if($officers->isEmpty())
      <x-data-table-empty icon="user" title="Belum ada petugas" message="Tambahkan petugas secara manual atau impor melalui Excel." />
    @else
      <div class="dt-table-responsive">
        <table class="dt-table officer-table" id="officerDataTable">
          <thead><tr><th>Petugas</th><th>Nomor ID</th><th>WhatsApp</th><th>Status</th><th>Terakhir masuk</th><th>Tindakan</th></tr></thead>
          <tbody>
            @foreach($officers as $officer)
              <tr class="dt-row" data-officer-record data-search="{{ strtolower($officer->name.' '.$officer->officer_id.' '.$officer->whatsapp) }}">
                <td><div class="dt-user-cell"><span class="dt-avatar officer-dt-avatar">{{ strtoupper(substr($officer->name, 0, 1)) }}</span><span class="dt-user-info"><strong class="dt-user-name">{{ $officer->name }}</strong><small class="dt-user-sub">Ditambahkan {{ $officer->created_at->format('d M Y') }}</small></span></div></td>
                <td><span class="dt-mono-ref">{{ $officer->officer_id }}</span></td>
                <td><strong class="officer-contact-value">{{ \App\Support\IndonesianPhoneNumber::mask($officer->whatsapp) }}</strong><small class="dt-user-sub">{{ match($officer->latestCredentialMessage?->status) { 'sent' => 'Akses terkirim', 'queued', 'processing' => 'Dalam antrean', 'failed' => 'Pengiriman gagal', 'skipped' => 'Pengiriman dilewati', default => 'Belum dikirim' } }}</small></td>
                <td><span class="dt-badge-status {{ $officer->is_active ? 'dt-status-approved' : 'dt-status-rejected' }}"><span class="dt-dot"></span>{{ $officer->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td><strong class="officer-contact-value">{{ $officer->last_authenticated_at?->format('d M Y') ?? 'Belum pernah' }}</strong>@if($officer->last_authenticated_at)<small class="dt-user-sub">{{ $officer->last_authenticated_at->format('H:i') }} WITA</small>@endif</td>
                <td><div class="officer-row-actions">
                  <form action="{{ route('admin.security-officers.reset-pin', $officer) }}" method="POST" data-confirm-reset-pin="{{ $officer->name }}">@csrf<button type="submit" class="admin-secondary-button" @disabled(!$officer->is_active)>Kirim ulang akses</button></form>
                  <form action="{{ route('admin.security-officers.status', $officer) }}" method="POST" @if($officer->is_active) data-confirm-officer-deactivate="{{ $officer->name }}" @endif>@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $officer->is_active ? 0 : 1 }}"><button type="submit" class="admin-status-button {{ $officer->is_active ? 'danger' : '' }}">{{ $officer->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                </div></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="dt-mobile-list officer-mobile-list">
        @foreach($officers as $officer)
          <article class="dt-mobile-card officer-mobile-item" data-officer-record data-search="{{ strtolower($officer->name.' '.$officer->officer_id.' '.$officer->whatsapp) }}">
            <div class="dt-mobile-card-header">
              <span class="dt-avatar officer-dt-avatar">{{ strtoupper(substr($officer->name, 0, 1)) }}</span>
              <div class="dt-mobile-card-main"><div class="dt-mobile-card-title-row"><strong class="dt-mobile-card-title">{{ $officer->name }}</strong><span class="dt-badge-status {{ $officer->is_active ? 'dt-status-approved' : 'dt-status-rejected' }}"><span class="dt-dot"></span>{{ $officer->is_active ? 'Aktif' : 'Nonaktif' }}</span></div><div class="dt-mobile-card-meta"><span class="dt-mono-ref">{{ $officer->officer_id }}</span><span>{{ \App\Support\IndonesianPhoneNumber::mask($officer->whatsapp) }}</span></div></div>
            </div>
            <div class="officer-mobile-details"><span>{{ match($officer->latestCredentialMessage?->status) { 'sent' => 'Akses terkirim', 'queued', 'processing' => 'Dalam antrean', 'failed' => 'Pengiriman gagal', 'skipped' => 'Pengiriman dilewati', default => 'Belum dikirim' } }}</span><span>Terakhir masuk: {{ $officer->last_authenticated_at?->format('d M Y, H:i') ?? 'belum pernah' }}</span></div>
            <div class="dt-mobile-card-footer officer-row-actions">
              <form action="{{ route('admin.security-officers.reset-pin', $officer) }}" method="POST" data-confirm-reset-pin="{{ $officer->name }}">@csrf<button type="submit" class="admin-secondary-button" @disabled(!$officer->is_active)>Kirim ulang akses</button></form>
              <form action="{{ route('admin.security-officers.status', $officer) }}" method="POST" @if($officer->is_active) data-confirm-officer-deactivate="{{ $officer->name }}" @endif>@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $officer->is_active ? 0 : 1 }}"><button type="submit" class="admin-status-button {{ $officer->is_active ? 'danger' : '' }}">{{ $officer->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
            </div>
          </article>
        @endforeach
      </div>
      <div id="officerFilterEmpty" class="admin-filter-empty" data-table-filter-empty hidden>
        <x-data-table-empty icon="search" title="Petugas tidak ditemukan" message="Ubah kata kunci pencarian untuk melihat data lainnya." />
      </div>
      <div class="dt-footer">
        <div class="dt-footer-info">Menampilkan <strong>{{ $officers->firstItem() ?? 0 }}</strong>–<strong>{{ $officers->lastItem() ?? 0 }}</strong> dari <strong>{{ $officers->total() }}</strong> petugas</div>
        <div class="dt-pagination-nav" role="navigation" aria-label="Navigasi halaman petugas">
          @if($officers->onFirstPage())<span class="dt-page-btn disabled">‹ Prev</span>@else<a href="{{ $officers->previousPageUrl() }}" class="dt-page-btn">‹ Prev</a>@endif
          @foreach($officers->getUrlRange(max(1, $officers->currentPage() - 2), min($officers->lastPage(), $officers->currentPage() + 2)) as $page => $url)
            @if($page === $officers->currentPage())<span class="dt-page-btn active" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" class="dt-page-btn">{{ $page }}</a>@endif
          @endforeach
          @if($officers->hasMorePages())<a href="{{ $officers->nextPageUrl() }}" class="dt-page-btn">Next ›</a>@else<span class="dt-page-btn disabled">Next ›</span>@endif
        </div>
      </div>
    @endif
  </section>
</div>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.querySelector('[data-officer-file]')?.addEventListener('change', function () {
  const label = document.querySelector('[data-officer-file-name]');
  if (label) label.textContent = this.files?.[0]?.name || 'Pilih file Excel';
});
document.querySelectorAll('[data-confirm-reset-pin]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    if (!window.confirm('Kirim nomor ID dan PIN baru kepada ' + form.dataset.confirmResetPin + '? PIN sebelumnya langsung tidak berlaku.')) event.preventDefault();
  });
});
document.querySelectorAll('[data-confirm-officer-deactivate]').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    if (!window.confirm('Nonaktifkan ' + form.dataset.confirmOfficerDeactivate + '? Sesi scanner petugas akan ditolak pada permintaan berikutnya.')) event.preventDefault();
  });
});

const officerTable = document.querySelector('[data-security-officer-table]');
if (officerTable) {
  const search = document.getElementById('officerSearch');
  const count = document.getElementById('officerRecordsCount');
  const empty = document.getElementById('officerFilterEmpty');
  const table = document.getElementById('officerDataTable');
  const filter = function () {
    const query = search?.value.trim().toLowerCase() || '';
    let visible = 0;
    officerTable.querySelectorAll('[data-officer-record]').forEach(function (record) {
      const matches = !query || (record.dataset.search || '').includes(query);
      record.hidden = !matches;
      if (matches && record.matches('tr')) visible += 1;
    });
    if (count) count.textContent = String(visible);
    if (empty) empty.hidden = visible !== 0;
  };
  search?.addEventListener('input', filter);
  filter();

  document.getElementById('officerPerPage')?.addEventListener('change', function (event) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', event.target.value);
    url.searchParams.delete('page');
    window.location.assign(url.toString());
  });
  document.getElementById('officerDensityNormal')?.addEventListener('click', function (event) {
    event.currentTarget.classList.add('active');
    document.getElementById('officerDensityCompact')?.classList.remove('active');
    table?.classList.remove('dt-table--compact');
  });
  document.getElementById('officerDensityCompact')?.addEventListener('click', function (event) {
    event.currentTarget.classList.add('active');
    document.getElementById('officerDensityNormal')?.classList.remove('active');
    table?.classList.add('dt-table--compact');
  });
}
</script>
@endsection
