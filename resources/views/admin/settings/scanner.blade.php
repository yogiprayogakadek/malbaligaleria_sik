@extends('layouts.admin')

@section('title', 'Akses Scanner : Admin MBG')
@section('page-title', 'Akses Scanner')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Scanner</span></div>
      <h1 class="page-title">Akses scanner security</h1>
      <p class="page-subtitle">Scanner tetap dapat dibuka tanpa login. Batasi lokasi penggunaannya bila diperlukan.</p>
    </div>
    <a href="{{ route('scanner.index') }}" class="admin-secondary-button" target="_blank" rel="noopener"><svg><use href="#i-scan"/></svg>Buka scanner</a>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>Periksa kembali pengaturan yang ditandai.</span></div>@endif

  <section class="admin-panel">
    <div class="admin-panel-header">
      <div><h2>Cakupan penggunaan</h2><p>Pembatasan lokasi diperiksa kembali oleh server pada setiap verifikasi QR.</p></div>
      <span class="admin-live-state {{ $setting->requiresLocation() ? 'is-closed' : 'is-open' }}"><span></span>{{ $setting->requiresLocation() ? 'Area terbatas' : 'Dari mana saja' }}</span>
    </div>

    <form action="{{ route('admin.settings.scanner.update') }}" method="POST" class="admin-form admin-scanner-form" data-scanner-settings novalidate>
      @csrf @method('PUT')

      <fieldset class="scanner-access-options">
        <legend>Pilih kebijakan akses</legend>
        <label class="scanner-access-option">
          <input type="radio" name="access_mode" value="anywhere" @checked(old('access_mode', $setting->access_mode) === 'anywhere')>
          <span><svg><use href="#i-scan"/></svg><span><strong>Dari mana saja</strong><small>Tidak meminta lokasi perangkat.</small></span></span>
        </label>
        <label class="scanner-access-option">
          <input type="radio" name="access_mode" value="geofence" @checked(old('access_mode', $setting->access_mode) === 'geofence')>
          <span><svg><use href="#i-location"/></svg><span><strong>Area tertentu</strong><small>Lokasi dan akurasinya diperiksa setiap kali surat diverifikasi.</small></span></span>
        </label>
        @error('access_mode')<span class="admin-field-error">{{ $message }}</span>@enderror
      </fieldset>

      <div class="scanner-location-fields" data-location-fields @if(old('access_mode', $setting->access_mode) !== 'geofence') hidden @endif>
        <div class="scanner-location-heading">
          <div><strong>Titik pusat dan radius</strong><span>Klik peta atau geser marker untuk menentukan area scanner.</span></div>
          <button type="button" class="admin-secondary-button" data-use-location><svg><use href="#i-location"/></svg>Gunakan lokasi perangkat</button>
        </div>

        <div class="scanner-map-wrap">
          <div class="scanner-map" data-scanner-map role="application" aria-label="Peta pemilihan titik scanner"></div>
          <p>Klik lokasi pada peta. Lingkaran biru menunjukkan radius akses yang disimpan.</p>
        </div>

        <div class="admin-form-grid scanner-coordinate-grid">
          <label>
            <span>Latitude</span>
            <input name="latitude" type="number" value="{{ old('latitude', $setting->latitude) }}" min="-90" max="90" step="0.0000001" inputmode="decimal" placeholder="-8.7212345" data-location-input>
            @error('latitude')<span class="admin-field-error">{{ $message }}</span>@enderror
          </label>
          <label>
            <span>Longitude</span>
            <input name="longitude" type="number" value="{{ old('longitude', $setting->longitude) }}" min="-180" max="180" step="0.0000001" inputmode="decimal" placeholder="115.1845678" data-location-input>
            @error('longitude')<span class="admin-field-error">{{ $message }}</span>@enderror
          </label>
          <label>
            <span>Radius akses</span>
            <div class="scanner-radius-input"><input name="radius_meters" type="number" value="{{ old('radius_meters', $setting->radius_meters ?? 200) }}" min="20" max="10000" step="10" inputmode="numeric" data-location-input><span>meter</span></div>
            <small>Perangkat juga harus memberikan akurasi lokasi yang tidak melebihi radius ini.</small>
            @error('radius_meters')<span class="admin-field-error">{{ $message }}</span>@enderror
          </label>
        </div>
        <p class="scanner-location-feedback" data-location-feedback role="status" aria-live="polite"></p>
      </div>

      <div class="admin-form-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan pengaturan</button></div>
    </form>
  </section>

  <div class="permit-alert permit-alert--info scanner-security-note" role="note"><svg><use href="#i-lock"/></svg><span>Geofence adalah kontrol operasional berbasis lokasi browser. Gunakan HTTPS agar kamera dan lokasi presisi dapat diakses pada perangkat security.</span></div>
</div>
@endsection

@push('scripts')
@vite('resources/js/admin-scanner-map.js')
@endpush
