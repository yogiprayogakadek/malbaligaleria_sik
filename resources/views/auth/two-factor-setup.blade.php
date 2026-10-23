@extends('layouts.auth')

@section('title', 'Aktifkan 2FA - Mal Bali Galeria')
@section('auth-tagline', 'Pengamanan Akun Internal')

@section('auth-card')
<div class="auth-tab-panel active two-factor-setup" role="main">
  <div class="auth-security-mark" aria-hidden="true"><svg><use href="#i-shield-check"/></svg></div>
  <div class="auth-heading auth-heading--password">
    <h1>{{ Auth::user()->hasConfirmedTwoFactor() ? 'Autentikasi dua langkah aktif' : ($twoFactorRequired ? 'Aktifkan autentikasi dua langkah' : 'Autentikasi dua langkah tidak diwajibkan') }}</h1>
    <p>{{ Auth::user()->hasConfirmedTwoFactor() ? ($twoFactorRequired ? 'Akun internal ini meminta kode tambahan setiap kali masuk pada sesi baru.' : 'Konfigurasi akun tetap tersimpan dan dapat digunakan kembali saat kebijakan diaktifkan.') : ($twoFactorRequired ? 'Akun staf wajib dilindungi dengan aplikasi autentikator sebelum dashboard dapat digunakan.' : 'Anda dapat kembali ke dashboard tanpa menyiapkan aplikasi autentikator.') }}</p>
  </div>

  @if(session('success'))<div class="auth-alert auth-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="auth-alert auth-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif
  @unless($twoFactorRequired)<div class="auth-alert auth-alert--info" role="status"><svg><use href="#i-info"/></svg><span>Administrator sedang menonaktifkan kewajiban 2FA. Konfigurasi tersimpan, tetapi kode autentikator tidak diminta saat login.</span></div>@endunless

  @if($recoveryCodes)
    <section class="two-factor-recovery" aria-labelledby="recoveryTitle">
      <h2 id="recoveryTitle">Simpan recovery code ini</h2>
      <p>Setiap kode hanya dapat digunakan satu kali. Kode tidak akan ditampilkan kembali.</p>
      <div class="two-factor-code-grid">
        @foreach($recoveryCodes as $recoveryCode)<code>{{ $recoveryCode }}</code>@endforeach
      </div>
    </section>
  @elseif(! Auth::user()->hasConfirmedTwoFactor() && $twoFactorRequired)
    <div class="two-factor-qr" aria-label="QR code autentikator">{!! $qrCode !!}</div>
    <div class="two-factor-secret">
      <span>Tidak dapat memindai QR?</span>
      <code>{{ $secret }}</code>
    </div>
    <ol class="two-factor-steps">
      <li>Buka Google Authenticator, Microsoft Authenticator, atau aplikasi TOTP lainnya.</li>
      <li>Scan QR di atas, lalu masukkan kode 6 angka yang muncul.</li>
    </ol>
    <form action="{{ route('two-factor.setup.confirm') }}" method="POST">
      @csrf
      <div class="auth-field">
        <label for="setupCode">Kode autentikator</label>
        <div class="auth-input-wrap"><svg class="auth-input-icon"><use href="#i-lock"/></svg><input id="setupCode" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="000000"></div>
      </div>
      <button type="submit" class="btn-auth"><span class="btn-auth-label">Aktifkan 2FA</span></button>
    </form>
  @elseif(Auth::user()->hasConfirmedTwoFactor())
    <div class="auth-alert auth-alert--info"><svg><use href="#i-shield-check"/></svg><span>{{ $remainingRecoveryCodes }} recovery code masih tersedia.</span></div>
    <form action="{{ route('two-factor.setup.recovery-codes.regenerate') }}" method="POST">
      @csrf
      <div class="auth-field"><label for="recoveryPassword">Kata sandi saat ini</label><div class="auth-input-wrap"><svg class="auth-input-icon"><use href="#i-lock"/></svg><input id="recoveryPassword" name="current_password" type="password" autocomplete="current-password" required maxlength="255"></div></div>
      <div class="auth-field"><label for="recoveryCode">Kode autentikator</label><div class="auth-input-wrap"><svg class="auth-input-icon"><use href="#i-lock"/></svg><input id="recoveryCode" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></div></div>
      <button type="submit" class="btn-auth"><span class="btn-auth-label">Buat recovery code baru</span></button>
    </form>
  @endif

  @if(Auth::user()->hasConfirmedTwoFactor() || ! $twoFactorRequired)
    <a href="{{ $continueUrl ?? route(Auth::user()->dashboardRouteName()) }}" class="two-factor-back-link">
      {{ $continueUrl ? 'Lanjutkan ke permohonan' : 'Kembali ke dashboard' }}
    </a>
  @else
    <form action="{{ route('logout') }}" method="POST" class="forced-password-logout">@csrf<button type="submit">Keluar dari akun</button></form>
  @endif
</div>
@endsection
