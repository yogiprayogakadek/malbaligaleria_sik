@extends('layouts.admin')

@section('title', 'Keamanan Sistem : Admin MBG')
@section('page-title', 'Keamanan Sistem')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Keamanan</span></div>
      <h1 class="page-title">Keamanan login staf</h1>
      <p class="page-subtitle">Kelola autentikasi dua langkah, peringatan percobaan login, dan pantau perangkat baru.</p>
    </div>
    <span class="admin-live-state {{ $setting->staff_two_factor_required ? 'is-open' : 'is-closed' }}"><span></span>{{ $setting->staff_two_factor_required ? '2FA wajib' : '2FA nonaktif' }}</span>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali pengaturan yang ditandai.</span></div>@endif

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Kebijakan autentikasi dua langkah</h2><p>Perubahan berlaku pada proses login staf berikutnya.</p></div>
      <span class="admin-security-label"><svg><use href="#i-shield-check"/></svg>Konfirmasi admin</span>
    </div>

    <form action="{{ route('admin.settings.security.update') }}" method="POST" class="admin-form admin-security-form" autocomplete="off">
      @csrf @method('PUT')
      <fieldset class="scanner-access-options security-policy-options">
        <legend>Pilih kebijakan</legend>
        <label class="scanner-access-option">
          <input type="radio" name="staff_two_factor_required" value="1" @checked((string) old('staff_two_factor_required', (int) $setting->staff_two_factor_required) === '1')>
          <span><svg><use href="#i-shield-check"/></svg><span><strong>Wajib untuk seluruh staf</strong><small>Staf yang belum menyiapkan 2FA diarahkan ke proses aktivasi sebelum membuka dashboard.</small></span></span>
        </label>
        <label class="scanner-access-option">
          <input type="radio" name="staff_two_factor_required" value="0" @checked((string) old('staff_two_factor_required', (int) $setting->staff_two_factor_required) === '0')>
          <span><svg><use href="#i-lock"/></svg><span><strong>Tidak digunakan</strong><small>Staf masuk menggunakan email atau nomor HP dan kata sandi tanpa kode autentikator.</small></span></span>
        </label>
        @error('staff_two_factor_required')<span class="admin-field-error">{{ $message }}</span>@enderror
      </fieldset>

      <fieldset class="scanner-access-options security-policy-options security-login-alert-options">
        <legend>Peringatan aktivitas login</legend>
        <label class="scanner-access-option">
          <input type="radio" name="login_alerts_enabled" value="1" @checked((string) old('login_alerts_enabled', (int) $setting->login_alerts_enabled) === '1')>
          <span><svg><use href="#i-bell"/></svg><span><strong>Aktifkan peringatan</strong><small>Perangkat baru diberitahukan kepada pemilik akun. Admin juga menerima peringatan saat login staf gagal berulang.</small></span></span>
        </label>
        <label class="scanner-access-option">
          <input type="radio" name="login_alerts_enabled" value="0" @checked((string) old('login_alerts_enabled', (int) $setting->login_alerts_enabled) === '0')>
          <span><svg><use href="#i-bell"/></svg><span><strong>Hanya simpan riwayat</strong><small>Aktivitas tetap dicatat, tetapi email dan push keamanan tidak dikirim.</small></span></span>
        </label>
        @error('login_alerts_enabled')<span class="admin-field-error">{{ $message }}</span>@enderror
      </fieldset>

      <div class="security-alert-threshold">
        <label for="failedLoginAlertThreshold">
          <span>Ambang percobaan login gagal</span>
          <select id="failedLoginAlertThreshold" name="failed_login_alert_threshold" required>
            @foreach([3, 4, 5] as $threshold)
              <option value="{{ $threshold }}" @selected((int) old('failed_login_alert_threshold', $setting->failed_login_alert_threshold) === $threshold)>{{ $threshold }} kali</option>
            @endforeach
          </select>
          <small>Peringatan dikirim satu kali ketika jumlah kegagalan mencapai batas ini dalam lima menit.</small>
          @error('failed_login_alert_threshold')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>
      </div>

      <fieldset class="scanner-access-options security-policy-options">
        <legend>Konfirmasi perubahan sensitif</legend>
        <label class="scanner-access-option"><input type="radio" name="sensitive_actions_require_2fa" value="1" @checked((string)old('sensitive_actions_require_2fa',(int)$setting->sensitive_actions_require_2fa)==='1')><span><svg><use href="#i-lock"/></svg><span><strong>Wajibkan kode 2FA</strong><small>Perubahan kategori, tenant, template, integrasi, akun staf, dan monitoring memerlukan verifikasi ulang setiap 10 menit.</small></span></span></label>
        <label class="scanner-access-option"><input type="radio" name="sensitive_actions_require_2fa" value="0" @checked((string)old('sensitive_actions_require_2fa',(int)$setting->sensitive_actions_require_2fa)==='0')><span><svg><use href="#i-info"/></svg><span><strong>Cukup kata sandi</strong><small>Formulir sensitif tetap meminta kata sandi admin, tanpa verifikasi ulang kode autentikator.</small></span></span></label>
      </fieldset>

      <div class="security-setting-confirmation">
        <label>
          <span>Kata sandi admin saat ini</span>
          <input type="password" name="current_password" required maxlength="255" autocomplete="current-password">
          <small>Diperlukan setiap kali kebijakan keamanan diubah.</small>
          @error('current_password')<span class="admin-field-error">{{ $message }}</span>@enderror
        </label>
      </div>

      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan kebijakan</button></div>
    </form>
  </section>

  <section class="admin-panel security-event-panel" id="riwayat-login-staf">
    <div class="admin-panel-header">
      <div><h2>Riwayat keamanan staf</h2><p>Aktivitas terbaru dari seluruh akun staf. Data IP dan perangkat disimpan terenkripsi.</p></div>
    </div>
    <div class="security-event-list">
      @forelse($loginEvents as $event)
        <article class="security-event-row security-event-row--{{ $event->event }}">
          <span class="security-event-icon" aria-hidden="true"><svg><use href="#i-{{ $event->event === 'failed_attempts' ? 'info' : ($event->event === 'new_device' ? 'phone' : 'check') }}"/></svg></span>
          <div>
            <strong>{{ $event->title() }}</strong>
            <span>{{ $event->user->name }} · {{ $event->device_label }} · {{ $event->browser_label }}</span>
          </div>
          <time datetime="{{ $event->occurred_at->toIso8601String() }}">{{ $event->occurred_at->format('d/m/Y H:i') }} WITA</time>
        </article>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-shield-check"/></svg><strong>Belum ada aktivitas login</strong><span>Riwayat keamanan staf akan muncul di sini.</span></div>
      @endforelse
    </div>
  </section>

  <div class="permit-alert permit-alert--info scanner-security-note" role="note"><svg><use href="#i-info"/></svg><span>Menonaktifkan 2FA tidak menghapus secret atau recovery code. Mematikan peringatan juga tidak menghentikan pencatatan riwayat keamanan.</span></div>
</div>
@endsection
