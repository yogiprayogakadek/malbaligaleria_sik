@extends('layouts.admin')

@section('title', 'Pemeriksaan Upload : Admin MBG')
@section('page-title', 'Pemeriksaan Upload')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page upload-security-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Sistem</span><svg><use href="#i-chevron-right"/></svg><span>Pemeriksaan upload</span></div>
      <h1 class="page-title">Pemeriksaan keamanan file</h1>
      <p class="page-subtitle">Atur pemindaian malware untuk dokumen identitas, surat, bukti pembayaran, template, dan lampiran lainnya.</p>
    </div>
    <span class="admin-live-state {{ $setting->malware_scanning_enabled && $serverScanningEnabled ? 'is-open' : 'is-closed' }}"><span></span>{{ $setting->malware_scanning_enabled && $serverScanningEnabled ? 'Pemindaian aktif' : 'Pemindaian nonaktif' }}</span>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  @unless($serverScanningEnabled && $scannerBinaryReady)
    <div class="permit-alert permit-alert--warning" role="alert"><svg><use href="#i-info"/></svg><span>Scanner server belum siap. Pastikan <code>MALWARE_SCAN_ENABLED=true</code>, binary {{ $scannerBinaryName }} tersedia, dan PHP-FPM dapat menjalankannya.</span></div>
  @endunless

  @unless($setting->malware_scanning_enabled)
    <div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-lock"/></svg><div><strong>File masuk tanpa pemindaian malware.</strong><span>{{ $setting->disabled_reason }}</span></div></div>
  @endunless

  <section class="admin-panel upload-security-panel">
    <div class="admin-panel-header"><div><h2>Kebijakan pemeriksaan</h2><p>Validasi tipe, ekstensi, dan ukuran file tetap berjalan meskipun pemindaian malware dimatikan.</p></div><span class="admin-security-label"><svg><use href="#i-shield-check"/></svg>Diaudit</span></div>
    <form action="{{ route('admin.settings.upload-security.update') }}" method="POST" class="admin-form upload-security-form" autocomplete="off">
      @csrf @method('PUT')
      <fieldset class="scanner-access-options security-policy-options">
        <legend>Pilih kebijakan</legend>
        <label class="scanner-access-option">
          <input type="radio" name="malware_scanning_enabled" value="1" @checked((string)old('malware_scanning_enabled',(int)$setting->malware_scanning_enabled)==='1')>
          <span><svg><use href="#i-shield-check"/></svg><span><strong>Periksa seluruh file</strong><small>File hanya diterima setelah lolos pemeriksaan ClamAV. Jika scanner bermasalah, upload ditolak.</small></span></span>
        </label>
        <label class="scanner-access-option">
          <input type="radio" name="malware_scanning_enabled" value="0" @checked((string)old('malware_scanning_enabled',(int)$setting->malware_scanning_enabled)==='0')>
          <span><svg><use href="#i-info"/></svg><span><strong>Nonaktifkan sementara</strong><small>Gunakan hanya saat pemulihan scanner. Pembatasan format dan ukuran file tetap diberlakukan.</small></span></span>
        </label>
      </fieldset>

      <div class="upload-security-fields">
        <label><span>Alasan penonaktifan</span><textarea name="disabled_reason" rows="3" minlength="10" maxlength="500" placeholder="Contoh: layanan ClamAV sedang dalam pemeliharaan">{{ old('disabled_reason',$setting->disabled_reason) }}</textarea><small>Wajib diisi ketika pemindaian dinonaktifkan dan tersimpan dalam audit log.</small>@error('disabled_reason')<span class="admin-field-error">{{ $message }}</span>@enderror</label>
        <label><span>Kata sandi admin saat ini</span><input type="password" name="current_password" required maxlength="255" autocomplete="current-password"><small>Diperlukan setiap kali kebijakan diubah.</small>@error('current_password')<span class="admin-field-error">{{ $message }}</span>@enderror</label>
      </div>
      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan kebijakan</button></div>
    </form>
  </section>

  <div class="permit-alert permit-alert--info scanner-security-note" role="note"><svg><use href="#i-info"/></svg><span>Rekomendasi operasional: biarkan pemindaian aktif. Penonaktifan tidak memperbaiki ClamAV dan hanya disediakan agar layanan dapat dipulihkan secara terkontrol.</span></div>
</div>
@endsection
