@extends('layouts.admin')

@section('title', 'SLA Antrean : Admin MBG')
@section('page-title', 'SLA Antrean')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>SLA antrean</span></div>
      <h1 class="page-title">Batas waktu pemeriksaan</h1>
      <p class="page-subtitle">Tentukan lama maksimum permohonan menunggu tindakan staf pada setiap tahap.</p>
    </div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali batas waktu yang ditandai.</span></div>@endif

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Durasi per jenis permohonan</h2><p>Rentang yang dapat digunakan adalah 1 sampai 168 jam.</p></div></div>
    <form action="{{ route('admin.settings.sla.update') }}" method="POST" class="admin-form admin-mail-form">
      @csrf @method('PUT')
      <div class="admin-form-grid">
        <label><span>Loading / unloading</span><input type="number" name="loading_hours" value="{{ old('loading_hours', $setting->loading_hours) }}" min="1" max="168" step="1" required inputmode="numeric"><small>Pengingat dikirim jika antrean TR belum diperiksa.</small>@error('loading_hours')<span class="admin-field-error">{{ $message }}</span>@enderror</label>
        <label><span>Surat izin kerja</span><input type="number" name="work_hours" value="{{ old('work_hours', $setting->work_hours) }}" min="1" max="168" step="1" required inputmode="numeric"><small>Durasi dimulai ulang saat permohonan berpindah ke tahap staf berikutnya.</small>@error('work_hours')<span class="admin-field-error">{{ $message }}</span>@enderror</label>
      </div>
      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan batas waktu</button></div>
    </form>
  </section>

  <div class="permit-alert permit-alert--info scanner-security-note" role="note"><svg><use href="#i-info"/></svg><span>Waktu ketika permohonan menunggu pembayaran atau perbaikan dari pemohon tidak dihitung. Eskalasi dikirim satu kali pada setiap tahap kepada admin dan divisi yang bertugas.</span></div>
</div>
@endsection
