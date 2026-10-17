@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi : Mal Bali Galeria')

@section('auth-card')
<div class="auth-tab-panel active" role="tabpanel">
  <div class="auth-heading auth-heading--password">
    <h1>Atur ulang kata sandi</h1>
    <p>Masukkan email atau nomor HP yang terdaftar. Kami akan mengirim tautan pemulihan melalui kanal tersebut.</p>
  </div>

  @if (session('success'))
    <div class="auth-alert auth-alert--success" role="status">
      <svg aria-hidden="true"><use href="#i-check"/></svg>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if ($errors->any())
    <div class="auth-alert auth-alert--error" role="alert">
      <svg aria-hidden="true"><use href="#i-info"/></svg>
      <span>{{ $errors->first() }}</span>
    </div>
  @endif

  <form action="{{ route('password.email') }}" method="POST">
    @csrf
    <div class="auth-field">
      <label for="resetIdentifier">Email atau Nomor HP <span class="required" aria-hidden="true">*</span></label>
      <div class="auth-input-wrap">
        <svg class="auth-input-icon" aria-hidden="true"><use href="#i-user"/></svg>
        <input id="resetIdentifier" name="identifier" type="text" value="{{ old('identifier') }}"
               autocomplete="username" placeholder="nama@tenant.com atau 08123456789" required autofocus>
      </div>
    </div>

    <button type="submit" class="btn-auth"><span class="btn-auth-label">Kirim tautan pemulihan</span></button>
  </form>

  <p class="auth-switch"><a href="{{ route('login') }}" class="auth-switch-link">Kembali ke halaman masuk</a></p>
</div>
@endsection
