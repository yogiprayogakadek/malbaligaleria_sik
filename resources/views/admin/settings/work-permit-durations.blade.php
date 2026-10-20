@extends('layouts.admin')

@section('title', 'Durasi Izin Kerja : Admin MBG')
@section('page-title', 'Durasi Izin Kerja')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="admin-detail-heading">
    <div class="page-breadcrumb"><span>Sistem</span><svg><use href="#i-chevron-right"/></svg><span>Durasi izin kerja</span></div>
    <h1>Batas durasi izin kerja</h1>
    <p>Aturan ini menentukan tanggal selesai maksimum dan tetap diverifikasi oleh server.</p>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <section class="admin-panel">
    <form method="POST" action="{{ route('admin.settings.work-permit-durations.update') }}" class="admin-form admin-settings-form">
      @csrf @method('PUT')
      <div class="admin-form-grid">
        <label><span>Pekerjaan umum dan lainnya</span><input type="number" name="default_days" value="{{ old('default_days', $setting->default_days) }}" min="1" max="30" required><small>Hari kalender, termasuk tanggal mulai.</small></label>
        <label><span>Fit Out / Renovasi</span><input type="number" name="fit_out_days" value="{{ old('fit_out_days', $setting->fit_out_days) }}" min="1" max="90" required><small>Default 30 hari dan dapat disesuaikan.</small></label>
        <label><span>Pembongkaran</span><input type="number" name="demolition_days" value="{{ old('demolition_days', $setting->demolition_days) }}" min="1" max="30" required><small>Default 14 hari dan dapat disesuaikan.</small></label>
        <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
      </div>
      <div class="admin-form-actions"><button class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan durasi</button></div>
    </form>
  </section>
</div>
@endsection
