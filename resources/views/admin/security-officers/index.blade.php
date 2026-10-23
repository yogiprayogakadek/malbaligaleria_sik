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

  <section class="admin-panel officer-list-panel">
    <div class="admin-panel-header"><div><h2>Daftar petugas</h2><p>{{ $officers->total() }} petugas pada filter ini.</p></div></div>
    @if($officers->isEmpty())
      <x-data-table-empty icon="user" title="Belum ada petugas" message="Tambahkan petugas secara manual atau impor melalui Excel." />
    @else
      <div class="admin-table-wrap">
        <table class="admin-table officer-table">
          <thead><tr><th>Petugas</th><th>Nomor ID</th><th>WhatsApp</th><th>Status</th><th>Terakhir masuk</th><th>Tindakan</th></tr></thead>
          <tbody>
            @foreach($officers as $officer)
              <tr>
                <td><strong>{{ $officer->name }}</strong><small>Ditambahkan {{ $officer->created_at->format('d M Y') }}</small></td>
                <td><code>{{ $officer->officer_id }}</code></td>
                <td>{{ \App\Support\IndonesianPhoneNumber::mask($officer->whatsapp) }}<small>{{ match($officer->latestCredentialMessage?->status) { 'sent' => 'Akses terkirim', 'queued', 'processing' => 'Dalam antrean', 'failed' => 'Pengiriman gagal', 'skipped' => 'Pengiriman dilewati', default => 'Belum dikirim' } }}</small></td>
                <td><span class="dt-badge-status {{ $officer->is_active ? 'dt-status-approved' : 'dt-status-rejected' }}"><span class="dt-dot"></span>{{ $officer->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                <td>{{ $officer->last_authenticated_at?->format('d M Y, H:i') ?? 'Belum pernah' }}</td>
                <td><div class="officer-row-actions">
                  <form action="{{ route('admin.security-officers.reset-pin', $officer) }}" method="POST" data-confirm-reset-pin="{{ $officer->name }}">@csrf<button type="submit" class="admin-secondary-button" @disabled(!$officer->is_active)>Kirim ulang akses</button></form>
                  <form action="{{ route('admin.security-officers.status', $officer) }}" method="POST" @if($officer->is_active) data-confirm-officer-deactivate="{{ $officer->name }}" @endif>@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $officer->is_active ? 0 : 1 }}"><button type="submit" class="admin-status-button {{ $officer->is_active ? 'danger' : '' }}">{{ $officer->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
                </div></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="admin-mobile-list officer-mobile-list">
        @foreach($officers as $officer)
          <article class="officer-mobile-item">
            <div><strong>{{ $officer->name }}</strong><code>{{ $officer->officer_id }}</code><small>{{ \App\Support\IndonesianPhoneNumber::mask($officer->whatsapp) }} · {{ match($officer->latestCredentialMessage?->status) { 'sent' => 'akses terkirim', 'queued', 'processing' => 'dalam antrean', 'failed' => 'pengiriman gagal', 'skipped' => 'pengiriman dilewati', default => 'belum dikirim' } }}</small></div>
            <span class="dt-badge-status {{ $officer->is_active ? 'dt-status-approved' : 'dt-status-rejected' }}"><span class="dt-dot"></span>{{ $officer->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            <div class="officer-row-actions">
              <form action="{{ route('admin.security-officers.reset-pin', $officer) }}" method="POST" data-confirm-reset-pin="{{ $officer->name }}">@csrf<button type="submit" class="admin-secondary-button" @disabled(!$officer->is_active)>Kirim ulang akses</button></form>
              <form action="{{ route('admin.security-officers.status', $officer) }}" method="POST" @if($officer->is_active) data-confirm-officer-deactivate="{{ $officer->name }}" @endif>@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $officer->is_active ? 0 : 1 }}"><button type="submit" class="admin-status-button {{ $officer->is_active ? 'danger' : '' }}">{{ $officer->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
            </div>
          </article>
        @endforeach
      </div>
      <div class="dt-footer">{{ $officers->links() }}</div>
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
</script>
@endsection
