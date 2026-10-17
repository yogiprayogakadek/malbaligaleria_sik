@php
  $accountLayout = auth()->user()->isAdmin()
    ? 'layouts.admin'
    : ((auth()->user()->isValidator() || auth()->user()->isSecretary()) ? 'layouts.validator' : 'layouts.portal');
@endphp
@extends($accountLayout)

@section('title', 'Profil Akun - Mal Bali Galeria')
@section('page-title', 'Profil Akun')

@section('content')
<section class="account-profile-page">
  <header class="account-security-header">
    <div>
      <p class="account-security-eyebrow">IDENTITAS AKUN</p>
      <h1>Profil Anda</h1>
      <p>Email dan nomor HP digunakan untuk masuk serta memulihkan akun. Konfirmasi kata sandi diperlukan setiap kali menyimpan perubahan.</p>
    </div>
    <a href="{{ route('account.security.index') }}" class="account-profile-security"><svg><use href="#i-lock"/></svg>Keamanan akun</a>
  </header>

  @if(session('success'))<div class="account-security-alert account-security-alert--success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="account-security-alert account-security-alert--error" role="alert">Periksa kembali field yang ditandai.</div>@endif

  <form action="{{ route('account.profile.update') }}" method="POST" class="account-profile-form">
    @csrf @method('PUT')
    <div class="account-profile-grid">
      <label><span>Nama lengkap</span><input name="name" value="{{ old('name', auth()->user()->name) }}" maxlength="120" autocomplete="name" required>@error('name')<small>{{ $message }}</small>@enderror</label>
      @if(auth()->user()->isTenant())
        <label><span>Nama tenant / toko</span><input name="tenant_name" value="{{ old('tenant_name', auth()->user()->tenant_name) }}" maxlength="120" required>@error('tenant_name')<small>{{ $message }}</small>@enderror</label>
      @endif
      <label><span>Email</span><input name="email" type="email" value="{{ old('email', auth()->user()->email) }}" maxlength="120" autocomplete="email" required>@error('email')<small>{{ $message }}</small>@enderror</label>
      <label><span>Nomor HP / WhatsApp</span><input name="phone" type="tel" value="{{ old('phone', auth()->user()->phone) }}" maxlength="25" autocomplete="tel" inputmode="tel" required>@error('phone')<small>{{ $message }}</small>@enderror</label>
    </div>
    <div class="account-profile-confirm">
      <label><span>Kata sandi saat ini</span><input name="current_password" type="password" maxlength="255" autocomplete="current-password" required>@error('current_password')<small>{{ $message }}</small>@enderror</label>
      <p>Role dan divisi hanya dapat diubah oleh administrator melalui pengelolaan akun.</p>
    </div>
    <div class="account-profile-actions"><button type="submit"><svg><use href="#i-check"/></svg>Simpan profil</button></div>
  </form>
</section>
@endsection
