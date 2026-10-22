@extends('layouts.admin')

@section('title', 'Pola Nomor Surat - Admin MBG')
@section('page-title', 'Pola Nomor Surat')

@section('content')
<div class="page-wrap admin-page-wrap permit-number-page">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Sistem</span><svg><use href="#i-chevron-right"/></svg><span>Pola nomor surat</span></div><h1 class="page-title">Format nomor surat</h1><p class="page-subtitle">Atur format nomor baru tanpa mengubah nomor dan dokumen yang sudah diterbitkan.</p></div>
  </div>
  @if(session('success'))<div class="permit-alert permit-alert--success"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <div class="permit-number-layout">
    @foreach(['loading' => 'Loading / unloading', 'work' => 'Surat izin kerja', 'event' => 'Surat izin event'] as $type => $label)
      @php($item = $settings[$type])
      <section class="admin-panel permit-number-panel">
        <div class="admin-panel-header"><div><h2>{{ $label }}</h2><p>Contoh hasil berdasarkan tanggal hari ini.</p></div><span class="permit-number-preview">{{ $item['preview'] }}</span></div>
        <form method="POST" action="{{ route('admin.settings.permit-numbers.update') }}" class="permit-number-form">
          @csrf @method('PUT')
          <input type="hidden" name="permit_type" value="{{ $type }}">
          <label class="permit-number-pattern"><span>Pola nomor</span><input name="pattern" value="{{ old('permit_type') === $type ? old('pattern') : $item['setting']->pattern }}" maxlength="100" required><small>Wajib menggunakan <code>@{{sequence}}</code>. Placeholder lain bersifat opsional.</small></label>
          <div class="permit-number-grid">
            <label><span>Jumlah digit urutan</span><input type="number" name="sequence_padding" min="2" max="8" value="{{ old('permit_type') === $type ? old('sequence_padding') : $item['setting']->sequence_padding }}" required></label>
            <label><span>Reset nomor urut</span><select name="reset_period"><option value="global" @selected((old('permit_type') === $type ? old('reset_period') : $item['setting']->reset_period) === 'global')>Tidak direset</option><option value="yearly" @selected((old('permit_type') === $type ? old('reset_period') : $item['setting']->reset_period) === 'yearly')>Setiap tahun</option><option value="monthly" @selected((old('permit_type') === $type ? old('reset_period') : $item['setting']->reset_period) === 'monthly')>Setiap bulan</option></select></label>
          </div>
          <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
          <div class="admin-form-actions"><button class="admin-primary-button"><svg><use href="#i-check"/></svg>Simpan pola {{ $label }}</button></div>
        </form>
      </section>
    @endforeach
  </div>
  <section class="admin-panel permit-placeholder-panel"><div class="admin-panel-header"><div><h2>Placeholder yang tersedia</h2><p>Gunakan susunan yang sesuai dengan standar dokumen internal.</p></div></div><div class="permit-placeholder-list"><code>@{{month}}</code><span>Bulan dua digit</span><code>@{{month_roman}}</code><span>Bulan Romawi</span><code>@{{year}}</code><span>Tahun empat digit</span><code>@{{year_short}}</code><span>Tahun dua digit</span><code>@{{sequence}}</code><span>Nomor urut</span></div></section>
</div>
@endsection
