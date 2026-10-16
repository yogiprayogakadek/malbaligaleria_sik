@extends('layouts.admin')

@section('title', 'Konfirmasi Retry : Admin MBG')
@section('page-title', 'Konfirmasi Retry')

@section('content')
<div class="page-wrap admin-page-wrap admin-form-page">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><a href="{{ route('admin.failed-jobs.index') }}">Antrean gagal</a><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Konfirmasi retry</span></div>
      <h1 class="page-title">Masukkan kembali ke antrean</h1>
      <p class="page-subtitle">Pastikan penyebab gangguan sudah ditangani agar pekerjaan tidak gagal berulang.</p>
    </div>
    <a href="{{ route('admin.failed-jobs.index') }}" class="admin-secondary-button"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
  </div>

  <section class="admin-panel failed-job-confirm-panel">
    <div class="failed-job-confirm-meta">
      <div><span>Pekerjaan</span><strong>{{ $job->name }}</strong></div>
      <div><span>Waktu gagal</span><strong>{{ $job->failed_at->format('d M Y, H:i:s') }} WITA</strong></div>
      <div><span>Koneksi / queue</span><strong>{{ $job->connection }} / {{ $job->queue }}</strong></div>
      <div><span>Jenis kegagalan</span><strong>{{ $job->failure_type }}</strong></div>
    </div>

    <form method="POST" action="{{ route('admin.failed-jobs.retry', $job->uuid) }}" class="admin-form failed-job-retry-form">
      @csrf
      <label>Kata sandi administrator
        <input type="password" name="password" required autocomplete="current-password" aria-describedby="passwordHelp">
        <small id="passwordHelp">Diperlukan untuk mencegah retry tanpa sengaja atau tanpa otorisasi.</small>
        @error('password')<span class="admin-field-error">{{ $message }}</span>@enderror
      </label>
      <div class="admin-form-actions">
        <a href="{{ route('admin.failed-jobs.index') }}" class="admin-secondary-button">Batal</a>
        <button type="submit" class="admin-primary-button">Masukkan ke antrean</button>
      </div>
    </form>
  </section>
</div>
@endsection
