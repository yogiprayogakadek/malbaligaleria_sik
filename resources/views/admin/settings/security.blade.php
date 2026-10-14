@extends('layouts.admin')

@section('title', 'Keamanan Sistem : Admin MBG')
@section('page-title', 'Keamanan Sistem')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Keamanan</span></div>
      <h1 class="page-title">Autentikasi staf</h1>
      <p class="page-subtitle">Atur apakah admin, validator, dan secretary wajib memasukkan kode autentikator saat login.</p>
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

  <div class="permit-alert permit-alert--info scanner-security-note" role="note"><svg><use href="#i-info"/></svg><span>Menonaktifkan kebijakan tidak menghapus secret atau recovery code yang sudah dimiliki staf. Data tersebut dapat digunakan kembali saat 2FA diwajibkan lagi.</span></div>
</div>
@endsection
