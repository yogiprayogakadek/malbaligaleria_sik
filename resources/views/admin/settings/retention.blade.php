@extends('layouts.admin')

@section('title', 'Retensi Dokumen : Admin MBG')
@section('page-title', 'Retensi Dokumen')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Retensi dokumen</span></div>
      <h1 class="page-title">Masa simpan dokumen sensitif</h1>
      <p class="page-subtitle">Hapus file privat setelah proses izin selesai tanpa menghapus data permohonan dan audit.</p>
    </div>
    <span class="admin-live-state {{ $setting->enabled ? 'is-open' : 'is-closed' }}"><span></span>{{ $setting->enabled ? 'Aktif' : 'Nonaktif' }}</span>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if(session('retention_preview'))<div class="permit-alert permit-alert--info" role="status"><svg><use href="#i-info"/></svg><span>{{ session('retention_preview') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali kebijakan yang ditandai.</span></div>@endif

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Kebijakan penghapusan</h2><p>Perhitungan dimulai setelah izin berakhir atau mencapai status final.</p></div>
      <span class="admin-security-label"><svg><use href="#i-shield-check"/></svg>Konfirmasi admin</span>
    </div>

    <form action="{{ route('admin.settings.retention.update') }}" method="POST" class="admin-form admin-retention-form" autocomplete="off">
      @csrf @method('PUT')
      <fieldset class="scanner-access-options security-policy-options">
        <legend>Status penghapusan otomatis</legend>
        <label class="scanner-access-option">
          <input type="radio" name="enabled" value="1" @checked((string) old('enabled', (int) $setting->enabled) === '1')>
          <span><svg><use href="#i-trash"/></svg><span><strong>Aktifkan penghapusan terjadwal</strong><small>Scheduler memeriksa dokumen setiap hari dan hanya memproses izin yang sudah tidak aktif.</small></span></span>
        </label>
        <label class="scanner-access-option">
          <input type="radio" name="enabled" value="0" @checked((string) old('enabled', (int) $setting->enabled) === '0')>
          <span><svg><use href="#i-lock"/></svg><span><strong>Nonaktifkan penghapusan</strong><small>File tetap tersimpan. Pemeriksaan kandidat masih dapat dijalankan tanpa menghapus data.</small></span></span>
        </label>
        @error('enabled')<span class="admin-field-error">{{ $message }}</span>@enderror
      </fieldset>

      <div class="admin-form-grid retention-duration-grid">
        <label><span>KTP / SIM</span><input type="number" name="identity_days" value="{{ old('identity_days', $setting->identity_days) }}" min="1" max="3650" step="1" required inputmode="numeric"><small>Termasuk dokumen identitas pada kedua jenis izin.</small>@error('identity_days')<span class="admin-field-error">{{ $message }}</span>@enderror</label>
        <label><span>Surat tugas</span><input type="number" name="assignment_days" value="{{ old('assignment_days', $setting->assignment_days) }}" min="1" max="3650" step="1" required inputmode="numeric"><small>Berlaku untuk lampiran pekerjaan rooftop dan ATM.</small>@error('assignment_days')<span class="admin-field-error">{{ $message }}</span>@enderror</label>
        <label><span>Bukti pembayaran dan refund</span><input type="number" name="financial_days" value="{{ old('financial_days', $setting->financial_days) }}" min="1" max="3650" step="1" required inputmode="numeric"><small>Dihitung setelah deposit dikembalikan atau permohonan ditolak.</small>@error('financial_days')<span class="admin-field-error">{{ $message }}</span>@enderror</label>
      </div>

      <div class="security-setting-confirmation">
        <label>
          <span>Kata sandi admin saat ini</span>
          <input type="password" name="current_password" required maxlength="255" autocomplete="current-password">
          <small>Diperlukan setiap kali masa simpan atau status penghapusan diubah.</small>
          @error('current_password')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>
      </div>

      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan kebijakan</button></div>
    </form>
  </section>

  <section class="admin-panel retention-preview-panel">
    <div class="admin-panel-header"><div><h2>Simulasi penghapusan</h2><p>Hitung file yang memenuhi kebijakan saat ini tanpa mengubah storage.</p></div></div>
    <form action="{{ route('admin.settings.retention.preview') }}" method="POST" class="retention-preview-action">
      @csrf
      <button type="submit" class="admin-secondary-button"><svg><use href="#i-search"/></svg>Periksa kandidat</button>
    </form>
  </section>

  <div class="permit-alert permit-alert--info scanner-security-note" role="note"><svg><use href="#i-info"/></svg><span>Path file dibatasi ke direktori dokumen yang dikenal. Permohonan aktif, proses pembayaran, pekerjaan berjalan, dan refund yang belum selesai tidak akan dibersihkan.</span></div>
</div>
@endsection
