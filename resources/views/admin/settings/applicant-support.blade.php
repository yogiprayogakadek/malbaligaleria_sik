@extends('layouts.admin')

@section('title', 'Bantuan Pemohon : Admin MBG')
@section('page-title', 'Bantuan Pemohon')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header"><div><div class="page-breadcrumb"><span>Komunikasi</span><svg><use href="#i-chevron-right"/></svg><span>Bantuan pemohon</span></div><h1 class="page-title">Kontak bantuan pemohon</h1><p class="page-subtitle">Atur kontak WhatsApp yang ditampilkan pada formulir dan halaman status permohonan.</p></div></div>
  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif
  <section class="admin-panel">
    <form method="POST" action="{{ route('admin.settings.applicant-support.update') }}" class="admin-form admin-settings-form">
      @csrf @method('PUT')
      <label class="admin-checkbox"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $setting->is_active))><span><strong>Tampilkan bantuan pada halaman permohonan</strong><small>Bubble tidak ditampilkan pada dashboard staf atau scanner.</small></span></label>
      <div class="admin-form-grid">
        <label><span>Nama kontak</span><input name="contact_name" value="{{ old('contact_name', $setting->contact_name) }}" maxlength="120" placeholder="Contoh: IT Support MBG"><small>Nama ini ditampilkan kepada pemohon.</small></label>
        <label><span>Nomor WhatsApp</span><input name="whatsapp_number" value="{{ old('whatsapp_number', $setting->whatsapp_number) }}" maxlength="25" inputmode="tel" placeholder="Contoh: 081234567890"><small>Nomor disimpan dalam format Indonesia 62.</small></label>
        <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
      </div>
      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan kontak bantuan</button></div>
    </form>
  </section>
</div>
@endsection
