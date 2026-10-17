@extends('layouts.auth')

@section('title', 'Kata Sandi Baru : Mal Bali Galeria')

@section('auth-card')
<div class="auth-tab-panel active" role="tabpanel">
  <div class="auth-heading auth-heading--password">
    <h1>Buat kata sandi baru</h1>
    <p>Gunakan sedikitnya 8 karakter dengan kombinasi huruf dan angka.</p>
  </div>

  @if ($errors->any())
    <div class="auth-alert auth-alert--error" role="alert">
      <svg aria-hidden="true"><use href="#i-info"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <form action="{{ route('password.update') }}" method="POST">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="auth-field">
      <label for="resetEmail">Email akun</label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-user"/></svg>
        <input id="resetEmail" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required readonly>
      </div>
    </div>

    <div class="auth-field">
      <label for="resetPassword">Kata sandi baru</label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="resetPassword" name="password" type="password" autocomplete="new-password" minlength="8" required>
      </div>
    </div>

    <div class="auth-field">
      <label for="resetPasswordConfirmation">Ulangi kata sandi baru</label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-lock"/></svg>
        <input id="resetPasswordConfirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
      </div>
    </div>

    <button type="submit" class="btn-auth"><span class="btn-auth-label">Simpan kata sandi baru</span></button>
  </form>

  <p class="auth-switch"><a href="{{ route('login') }}" class="auth-switch-link">Kembali ke halaman masuk</a></p>
</div>
@endsection
