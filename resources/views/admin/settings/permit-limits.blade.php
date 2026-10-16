@extends('layouts.admin')

@section('title', 'Batas Pengajuan : Admin MBG')
@section('page-title', 'Batas Pengajuan')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Batas pengajuan</span></div>
      <h1 class="page-title">Limiter permohonan tenant</h1>
      <p class="page-subtitle">Atur frekuensi pengajuan loading/unloading dan surat izin kerja dari satu tenant.</p>
    </div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali batas pengajuan yang ditandai.</span></div>@endif

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Kebijakan pengajuan</h2><p>Batas berlaku gabungan untuk seluruh jenis permohonan yang aktif.</p></div>
      <span class="admin-security-label"><svg><use href="#i-shield-check"/></svg>Perlindungan anti-spam</span>
    </div>

    <form action="{{ route('admin.settings.permit-limits.update') }}" method="POST" class="admin-form admin-mail-form">
      @csrf @method('PUT')
      <fieldset class="scanner-access-options permit-limit-options">
        <legend>Status limiter</legend>
        <label class="scanner-access-option">
          <input type="radio" name="enabled" value="1" @checked((bool) old('enabled', $setting->enabled))>
          <span><svg><use href="#i-shield-check"/></svg><span><strong>Aktif</strong><small>Batasi pengajuan per tenant atau alamat IP.</small></span></span>
        </label>
        <label class="scanner-access-option">
          <input type="radio" name="enabled" value="0" @checked(! (bool) old('enabled', $setting->enabled))>
          <span><svg><use href="#i-clock"/></svg><span><strong>Nonaktif sementara</strong><small>Gunakan hanya saat pengujian terkontrol.</small></span></span>
        </label>
        @error('enabled')<span class="admin-field-error">{{ $message }}</span>@enderror
      </fieldset>

      <div class="admin-form-grid permit-limit-grid">
        <label>
          <span>Maksimum per menit</span>
          <input type="number" name="max_per_minute" value="{{ old('max_per_minute', $setting->max_per_minute) }}" min="1" max="100" step="1" required inputmode="numeric">
          <small>Menahan pengiriman berulang dalam waktu singkat.</small>
          @error('max_per_minute')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>
        <label>
          <span>Maksimum per hari</span>
          <input type="number" name="max_per_day" value="{{ old('max_per_day', $setting->max_per_day) }}" min="1" max="1000" step="1" required inputmode="numeric">
          <small>Kuota direset otomatis setiap 24 jam.</small>
          @error('max_per_day')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>
      </div>

      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan batas pengajuan</button></div>
    </form>
  </section>

  <div class="permit-alert permit-alert--info scanner-security-note" role="note"><svg><use href="#i-info"/></svg><span>Tenant yang login dikenali dari akun. Pemohon tanpa login dikenali dari alamat IP. Menonaktifkan limiter menghapus batas aplikasi, tetapi validasi form dan perlindungan server tetap berjalan.</span></div>
</div>
@endsection
