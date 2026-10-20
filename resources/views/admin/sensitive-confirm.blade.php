@extends('layouts.admin')
@section('title', 'Konfirmasi Keamanan : Admin MBG')
@section('page-title', 'Konfirmasi Keamanan')
@section('content')
<div class="page-wrap admin-page-wrap admin-form-page"><div class="admin-detail-heading"><a href="{{ route('admin.dashboard') }}" class="back-link"><svg><use href="#i-arrow-left"/></svg>Kembali ke dashboard</a><h1>Verifikasi perubahan sensitif</h1><p>Masukkan kode enam digit dari aplikasi autentikator. Konfirmasi berlaku selama 10 menit pada sesi ini.</p></div><section class="admin-panel"><form method="POST" action="{{ route('admin.sensitive-confirm.verify') }}" class="admin-form admin-settings-form">@csrf<div class="security-setting-confirmation"><label><span>Kode autentikator</span><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>@error('code')<small class="admin-field-error">{{ $message }}</small>@enderror</label></div><div class="admin-form-actions"><button class="admin-primary-button"><svg><use href="#i-lock"/></svg>Verifikasi</button></div></form></section></div>
@endsection
