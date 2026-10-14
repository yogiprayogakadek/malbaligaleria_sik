@extends('layouts.auth')

@section('title', 'Verifikasi Dua Langkah - Mal Bali Galeria')
@section('auth-tagline', 'Verifikasi Keamanan Akun')

@section('auth-card')
<div class="auth-tab-panel active" role="main">
  <div class="auth-security-mark" aria-hidden="true"><svg><use href="#i-shield-check"/></svg></div>
  <div class="auth-heading auth-heading--password">
    <h1>Verifikasi dua langkah</h1>
    <p>Masukkan kode 6 angka dari aplikasi autentikator. Recovery code juga dapat digunakan jika perangkat tidak tersedia.</p>
  </div>

  @if($errors->any())
    <div class="auth-alert auth-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <form action="{{ route('two-factor.challenge.verify') }}" method="POST">
    @csrf
    <div class="auth-field">
      <label for="twoFactorCode">Kode autentikator atau recovery code</label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="twoFactorCode" name="code" type="text" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" required maxlength="32" autofocus placeholder="000000 atau ABCDE-23456">
      </div>
    </div>
    <button type="submit" class="btn-auth"><span class="btn-auth-label">Verifikasi dan masuk</span></button>
  </form>

  <a href="{{ route('login') }}" class="two-factor-back-link">Kembali ke halaman masuk</a>
</div>
@endsection
