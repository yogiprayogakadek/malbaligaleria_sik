@extends('layouts.admin')

@section('title', 'Antrean Gagal : Admin MBG')
@section('page-title', 'Antrean Gagal')

@section('content')
<div class="page-wrap admin-page-wrap">
  <div class="page-header admin-page-header">
    <div>
      <div class="page-breadcrumb"><span>Sistem</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Antrean gagal</span></div>
      <h1 class="page-title">Pekerjaan yang gagal diproses</h1>
      <p class="page-subtitle">Payload, isi pesan, dokumen, dan detail exception tidak ditampilkan pada halaman ini.</p>
    </div>
    <a href="{{ route('admin.health.index') }}" class="admin-secondary-button"><svg><use href="#i-shield-check"/></svg>Kesehatan sistem</a>
  </div>

  @if(session('success'))<div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>@endif

  <div class="system-summary failed-job-summary" aria-label="Ringkasan pekerjaan gagal">
    <div class="is-error"><span>Total gagal</span><strong>{{ $failedCount }}</strong></div>
    <div class="is-warning"><span>Gagal hari ini</span><strong>{{ $failedTodayCount }}</strong></div>
  </div>

  <section class="admin-panel">
    <div class="admin-panel-header"><div><h2>Daftar antrean gagal</h2><p>Retry memerlukan kata sandi administrator dan dicatat pada audit log.</p></div></div>
    <div class="admin-table-wrap failed-job-table-wrap">
      <table class="admin-table failed-job-table">
        <thead><tr><th>Waktu gagal</th><th>Pekerjaan</th><th>Koneksi</th><th>Jenis kegagalan</th><th>Aksi</th></tr></thead>
        <tbody>
          @forelse($jobs as $job)
            <tr>
              <td><strong>{{ $job->failed_at->format('d M Y') }}</strong><span>{{ $job->failed_at->format('H:i:s') }} WITA</span></td>
              <td><strong>{{ $job->name }}</strong><span>Ref. {{ Str::limit($job->uuid, 13, '...') }}</span></td>
              <td><strong>{{ $job->connection }}</strong><span>Queue: {{ $job->queue }}</span></td>
              <td><span class="admin-status admin-status--rejected">{{ $job->failure_type }}</span></td>
              <td><a href="{{ route('admin.failed-jobs.confirm', $job->uuid) }}" class="admin-secondary-button">Tinjau retry</a></td>
            </tr>
          @empty
            <tr><td colspan="5"><div class="admin-table-empty"><svg><use href="#i-check"/></svg><strong>Tidak ada pekerjaan gagal</strong><span>Worker queue tidak memiliki kegagalan yang menunggu tindakan.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="admin-mobile-list failed-job-mobile-list">
      @forelse($jobs as $job)
        <article class="failed-job-mobile-row">
          <div><strong>{{ $job->name }}</strong><span class="admin-status admin-status--rejected">{{ $job->failure_type }}</span></div>
          <p>{{ $job->connection }} / {{ $job->queue }}</p>
          <small>{{ $job->failed_at->format('d M Y, H:i:s') }} WITA · Ref. {{ Str::limit($job->uuid, 13, '...') }}</small>
          <a href="{{ route('admin.failed-jobs.confirm', $job->uuid) }}" class="admin-secondary-button">Tinjau retry</a>
        </article>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-check"/></svg><strong>Tidak ada pekerjaan gagal</strong><span>Worker queue tidak memiliki kegagalan yang menunggu tindakan.</span></div>
      @endforelse
    </div>

    @if($jobs->hasPages())
      <nav class="admin-pagination" aria-label="Navigasi halaman antrean gagal">
        @if($jobs->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $jobs->previousPageUrl() }}">Sebelumnya</a>@endif
        <small>Halaman {{ $jobs->currentPage() }} dari {{ $jobs->lastPage() }}</small>
        @if($jobs->hasMorePages())<a href="{{ $jobs->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
      </nav>
    @endif
  </section>
</div>
@endsection
