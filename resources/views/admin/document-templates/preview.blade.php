@extends('layouts.admin')

@section('title', 'Uji Template Surat - Admin MBG')
@section('page-title', 'Uji Template Surat')

@section('content')
<div class="page-wrap admin-page-wrap document-preview-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Sistem</span><svg><use href="#i-chevron-right"/></svg><a href="{{ route('admin.settings.document-templates.index') }}">Template surat</a><svg><use href="#i-chevron-right"/></svg><span>Uji preview</span></div>
      <h1 class="page-title">Uji {{ $template->typeLabel() }} · versi {{ $template->version }}</h1>
      <p class="page-subtitle">Render template dengan data contoh sebelum versi ini dapat diaktifkan.</p>
    </div>
    <a href="{{ route('admin.settings.document-templates.index') }}" class="admin-secondary-button"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
  </div>

  @if($errors->any())<div class="permit-alert permit-alert--error"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>@endif

  <section class="admin-panel document-preview-panel">
    <div class="admin-panel-header">
      <div><h2>Data contoh</h2><p>{{ $template->original_name }} · SHA-256 {{ substr($template->sha256, 0, 12) }}…</p></div>
      <span class="admin-security-label"><svg><use href="#i-lock"/></svg>Tidak disimpan sebagai permohonan</span>
    </div>

    <div class="document-preview-notice">
      <svg><use href="#i-info"/></svg>
      <p><strong>Gunakan data contoh.</strong> Jangan masukkan nama, nomor telepon, email, atau data pemohon sebenarnya. PDF ini hanya untuk memeriksa tata letak.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.document-templates.preview.store', $template) }}" target="_blank" class="document-preview-form">
      @csrf
      <div class="document-preview-grid">
        <label><span>{{ $template->permit_type === 'event' ? 'Nama EO / tenant' : 'Nama organisasi / tenant' }}</span><input name="organization_name" maxlength="120" value="{{ old('organization_name', $defaults['organization_name']) }}" required></label>
        <label><span>Nama pemohon</span><input name="applicant_name" maxlength="120" value="{{ old('applicant_name', $defaults['applicant_name']) }}" required></label>
        @if($template->permit_type !== 'loading')
          <label class="document-preview-wide"><span>Lokasi pekerjaan</span><input name="work_location" maxlength="160" value="{{ old('work_location', $defaults['work_location']) }}" required></label>
        @endif
        <label class="document-preview-wide"><span>{{ $template->permit_type === 'loading' ? 'Deskripsi barang' : 'Rincian pekerjaan' }}</span><textarea name="work_detail" maxlength="500" rows="3" required>{{ old('work_detail', $defaults['work_detail']) }}</textarea></label>
        <label><span>Tanggal mulai</span><input type="date" name="start_date" value="{{ old('start_date', $defaults['start_date']) }}" required></label>
        <label><span>Tanggal selesai</span><input type="date" name="end_date" value="{{ old('end_date', $defaults['end_date']) }}" required></label>
      </div>
      <div class="document-preview-submit">
        <label><span>Kata sandi admin</span><input type="password" name="current_password" autocomplete="current-password" required></label>
        <div><p>Preview PDF dibuka di tab baru. Aktivasi tetap dilakukan terpisah dari halaman daftar template.</p><button class="admin-primary-button" type="submit"><svg><use href="#i-file"/></svg>Buka preview PDF</button></div>
      </div>
    </form>
  </section>
</div>
@endsection
