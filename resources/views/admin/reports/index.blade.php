@extends('layouts.admin')

@section('title', 'Laporan Perizinan : Admin MBG')
@section('page-title', 'Laporan Perizinan')

@section('content')
@php $trendMax = max(1, (int) $trend->max('total')); @endphp
<div class="page-wrap admin-page-wrap report-page">
  <div class="page-header admin-page-header report-page-header">
    <div>
      <div class="page-breadcrumb"><span>Administrasi</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Laporan</span></div>
      <h1 class="page-title">Aktivitas permohonan</h1>
      <p class="page-subtitle">Rekap loading, unloading, dan surat izin kerja berdasarkan tanggal pengajuan.</p>
    </div>
    <div class="report-export-actions">
      <a href="{{ route('admin.reports.excel', $filters) }}" class="admin-secondary-button"><svg><use href="#i-file"/></svg>Excel</a>
      <a href="{{ route('admin.reports.pdf', $filters) }}" class="admin-primary-button"><svg><use href="#i-file"/></svg>PDF</a>
    </div>
  </div>

  @if($errors->any())
    <div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><span>{{ $errors->first() }}</span></div>
  @endif

  <section class="admin-panel report-filter-panel" aria-labelledby="reportFilterTitle">
    <div class="admin-panel-header"><div><h2 id="reportFilterTitle">Filter laporan</h2><p>Rentang maksimal 366 hari untuk menjaga kinerja server.</p></div></div>
    <form method="GET" action="{{ route('admin.reports.index') }}" class="report-filters">
      <label>Tanggal awal<input type="date" name="date_from" value="{{ $filters['date_from'] }}" max="{{ now(config('operating-hours.timezone'))->toDateString() }}" required></label>
      <label>Tanggal akhir<input type="date" name="date_to" value="{{ $filters['date_to'] }}" max="{{ now(config('operating-hours.timezone'))->toDateString() }}" required></label>
      <label>Jenis permohonan<select name="permit_type"><option value="all" @selected($filters['permit_type'] === 'all')>Semua jenis</option><option value="loading" @selected($filters['permit_type'] === 'loading')>Loading / Unloading</option><option value="work" @selected($filters['permit_type'] === 'work')>Surat Izin Kerja</option></select></label>
      <label>Status<select name="status_group"><option value="all" @selected($filters['status_group'] === 'all')>Semua status</option><option value="active" @selected($filters['status_group'] === 'active')>Masih diproses</option><option value="approved" @selected($filters['status_group'] === 'approved')>Disetujui</option><option value="rejected" @selected($filters['status_group'] === 'rejected')>Ditolak</option></select></label>
      <label class="report-search-field">Nomor surat, tenant, atau pemohon<input type="search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="Cari data"></label>
      <div class="report-filter-actions"><button type="submit" class="admin-primary-button"><svg><use href="#i-search"/></svg>Terapkan</button><a href="{{ route('admin.reports.index') }}" class="admin-secondary-button">Reset</a></div>
    </form>
  </section>

  <section class="report-summary" aria-label="Ringkasan laporan">
    <div><span>Total permohonan</span><strong>{{ number_format($summary['total']) }}</strong></div>
    <div><span>Masih diproses</span><strong>{{ number_format($summary['active']) }}</strong></div>
    <div><span>Disetujui</span><strong>{{ number_format($summary['approved']) }}</strong></div>
    <div><span>Ditolak</span><strong>{{ number_format($summary['rejected']) }}</strong></div>
  </section>

  <div class="report-analysis-grid">
    <section class="admin-panel report-trend-panel">
      <div class="admin-panel-header"><div><h2>Tren pengajuan</h2><p>{{ $trend->count() }} periode dengan aktivitas.</p></div></div>
      <div class="report-trend" role="img" aria-label="Grafik jumlah pengajuan per periode">
        @forelse($trend as $point)
          <div class="report-trend-row"><span>{{ $point->label }}</span><div><i style="--report-bar: {{ round(($point->total / $trendMax) * 100, 2) }}%"></i></div><strong>{{ number_format($point->total) }}</strong></div>
        @empty
          <div class="admin-table-empty"><svg><use href="#i-file"/></svg><strong>Belum ada tren</strong><span>Tidak ada pengajuan pada periode ini.</span></div>
        @endforelse
      </div>
    </section>

    <section class="admin-panel report-breakdown-panel">
      <div class="admin-panel-header"><div><h2>Distribusi status</h2><p>Rincian status aktual per jenis surat.</p></div></div>
      <div class="report-breakdown-list">
        @forelse($summary['by_status'] as $status)
          <div><span><strong>{{ $status->label }}</strong><small>{{ $status->permit_type === 'loading' ? 'Loading / Unloading' : 'Surat Izin Kerja' }}</small></span><b>{{ number_format($status->total) }}</b></div>
        @empty
          <div class="admin-table-empty"><svg><use href="#i-file"/></svg><strong>Belum ada data status</strong><span>Distribusi akan muncul setelah ada pengajuan.</span></div>
        @endforelse
      </div>
      <div class="report-type-totals"><span>Loading / Unloading <strong>{{ number_format($summary['loading']) }}</strong></span><span>Surat Izin Kerja <strong>{{ number_format($summary['work']) }}</strong></span></div>
    </section>
  </div>

  <section class="admin-panel report-data-panel">
    <div class="admin-panel-header"><div><h2>Rincian permohonan</h2><p>{{ number_format($rows->total()) }} data sesuai filter.</p></div></div>
    <div class="admin-table-wrap report-table-wrap">
      <table class="admin-table report-table">
        <thead><tr><th>Diajukan</th><th>Nomor surat</th><th>Tenant / pemohon</th><th>Jenis</th><th>Divisi</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse($rows as $row)
            <tr>
              <td><strong>{{ $row->created_at->format('d M Y') }}</strong><span>{{ $row->created_at->format('H:i') }} WITA</span></td>
              <td><code>{{ $row->permit_number }}</code><span>{{ $row->start_date->format('d M') }} - {{ $row->end_date->format('d M Y') }}</span></td>
              <td><strong>{{ $row->entity_name }}</strong><span>{{ $row->applicant_name }}</span></td>
              <td><strong>{{ $row->type_label }}</strong><span>{{ $row->subtype_label }}</span></td>
              <td><strong>{{ $row->assigned_division === 'MEP' ? 'Engineering' : $row->assigned_division }}</strong></td>
              <td><span class="report-status report-status--{{ $row->status_group }}">{{ $row->status_label }}</span></td>
              <td><a href="{{ $row->permit_type === 'loading' ? route('admin.loading.show', $row->detail_key) : route('staff.work-permits.show', $row->detail_key) }}" class="admin-row-action" aria-label="Lihat {{ $row->permit_number }}" title="Lihat detail"><svg><use href="#i-chevron-right"/></svg></a></td>
            </tr>
          @empty
            <tr><td colspan="7"><div class="admin-table-empty"><svg><use href="#i-file"/></svg><strong>Tidak ada data laporan</strong><span>Ubah periode atau filter untuk melihat data lain.</span></div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="admin-mobile-list report-mobile-list">
      @forelse($rows as $row)
        <a href="{{ $row->permit_type === 'loading' ? route('admin.loading.show', $row->detail_key) : route('staff.work-permits.show', $row->detail_key) }}" class="report-mobile-row">
          <div><code>{{ $row->permit_number }}</code><span class="report-status report-status--{{ $row->status_group }}">{{ $row->status_label }}</span></div>
          <strong>{{ $row->entity_name }}</strong><span>{{ $row->type_label }} &middot; {{ $row->subtype_label }}</span><small>{{ $row->created_at->format('d M Y, H:i') }} WITA &middot; {{ $row->assigned_division === 'MEP' ? 'Engineering' : $row->assigned_division }}</small>
        </a>
      @empty
        <div class="admin-table-empty"><svg><use href="#i-file"/></svg><strong>Tidak ada data laporan</strong><span>Ubah periode atau filter untuk melihat data lain.</span></div>
      @endforelse
    </div>

    @if($rows->hasPages())
      <nav class="admin-pagination" aria-label="Navigasi halaman laporan">
        @if($rows->onFirstPage())<span>Sebelumnya</span>@else<a href="{{ $rows->previousPageUrl() }}">Sebelumnya</a>@endif
        <small>Halaman {{ $rows->currentPage() }} dari {{ $rows->lastPage() }}</small>
        @if($rows->hasMorePages())<a href="{{ $rows->nextPageUrl() }}">Berikutnya</a>@else<span>Berikutnya</span>@endif
      </nav>
    @endif
  </section>
</div>
@endsection
