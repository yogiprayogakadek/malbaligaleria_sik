@extends('layouts.admin')

@section('title', 'Rekonsiliasi Akses Security - Admin MBG')
@section('page-title', 'Rekonsiliasi Akses')

@section('content')
<div class="page-wrap admin-page-wrap security-reconciliation-page">
  <div class="page-header admin-page-header">
    <div><div class="page-breadcrumb"><span>Perizinan</span><svg><use href="#i-chevron-right"/></svg><span>Rekonsiliasi akses</span></div><h1 class="page-title">Periksa akses yang belum tuntas</h1><p class="page-subtitle">Daftar ini membantu admin menindaklanjuti selisih antara surat resmi, pemindaian QR, dan catatan gerbang.</p></div>
  </div>

  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Periksa rentang tanggal:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <section class="admin-panel reconciliation-filter-panel">
    <form method="GET" action="{{ route('admin.security-reconciliation.index') }}" class="reconciliation-filter-form">
      <label><span>Dari tanggal</span><input type="date" name="date_from" value="{{ $filters['date_from'] }}" required></label>
      <label><span>Sampai tanggal</span><input type="date" name="date_to" value="{{ $filters['date_to'] }}" required></label>
      <button type="submit" class="admin-primary-button"><svg><use href="#i-search"/></svg>Periksa</button>
    </form>
    <p>Rentang maksimal 90 hari. Check-in yang belum ditutup tetap ditampilkan meski berada di luar rentang agar tidak terlewat.</p>
  </section>

  <section class="reconciliation-summary" aria-label="Ringkasan temuan">
    <article><span class="reconciliation-summary-icon is-unscanned"><svg><use href="#i-scan"/></svg></span><div><span>Belum dipindai</span><strong>{{ number_format($summary['unscanned']) }}</strong><small>Surat disetujui dalam rentang</small></div></article>
    <article><span class="reconciliation-summary-icon is-open"><svg><use href="#i-clock"/></svg></span><div><span>Belum check-out</span><strong>{{ number_format($summary['open_access']) }}</strong><small>Check-in terakhir masih terbuka</small></div></article>
    <article><span class="reconciliation-summary-icon is-outside"><svg><use href="#i-info"/></svg></span><div><span>Di luar waktu izin</span><strong>{{ number_format($summary['outside_window']) }}</strong><small>Di luar tanggal atau pukul 22.00–11.00</small></div></article>
  </section>

  <section class="admin-panel reconciliation-panel">
    <div class="admin-panel-header"><div><h2>Surat disetujui belum dipindai</h2><p>Menampilkan maksimal 25 surat pada rentang yang belum memiliki hasil pemindaian aktif.</p></div><span class="admin-status admin-status--pending">{{ $summary['unscanned'] }} temuan</span></div>
    <div class="admin-table-wrap reconciliation-table-wrap"><table class="admin-table reconciliation-table"><thead><tr><th>Surat</th><th>Tenant</th><th>Periode izin</th><th>Waktu</th><th>Tindakan</th></tr></thead><tbody>
      @forelse($unscannedPermits as $permit)
        <tr><td data-label="Surat"><strong>{{ $permit->permit_number }}</strong><span>Disetujui {{ $permit->reviewed_at?->format('d M Y, H:i') ?? '-' }} WITA</span></td><td data-label="Tenant">{{ $permit->tenant_name }}</td><td data-label="Periode">{{ $permit->start_date->format('d M Y') }}–{{ $permit->end_date->format('d M Y') }}</td><td data-label="Waktu">{{ $permit->movement_time_label }}</td><td data-label="Tindakan"><a href="{{ route('admin.loading.show', $permit->permit_number) }}" class="btn-dt-action btn-dt-action--view">Lihat surat</a></td></tr>
      @empty
        <tr class="reconciliation-empty"><td colspan="5"><x-data-table-empty icon="scan" title="Tidak ada surat yang tertinggal" message="Semua surat disetujui dalam rentang sudah memiliki pemindaian aktif." /></td></tr>
      @endforelse
    </tbody></table></div>
  </section>

  <section class="admin-panel reconciliation-panel">
    <div class="admin-panel-header"><div><h2>Check-in belum ditutup</h2><p>Catatan gerbang terakhir masih check-in dan memerlukan pemeriksaan petugas.</p></div><span class="admin-status admin-status--rejected">{{ $summary['open_access'] }} terbuka</span></div>
    <div class="admin-table-wrap reconciliation-table-wrap"><table class="admin-table reconciliation-table"><thead><tr><th>Check-in</th><th>Surat / tenant</th><th>Petugas</th><th>Usia catatan</th><th>Bukti</th></tr></thead><tbody>
      @forelse($openAccessEvents as $event)
        <tr><td data-label="Check-in"><strong>{{ $event->occurred_at->format('d M Y') }}</strong><span>{{ $event->occurred_at->format('H:i:s') }} WITA</span></td><td data-label="Surat / tenant"><strong>{{ $event->loadingPermit?->permit_number ?? 'Surat tidak tersedia' }}</strong><span>{{ $event->loadingPermit?->tenant_name ?? 'Referensi #'.$event->permit_id }}</span></td><td data-label="Petugas"><strong>{{ $event->securityOfficer?->name ?? 'Tidak tercatat' }}</strong><span>{{ $event->securityOfficer?->officer_id ?? '-' }}</span></td><td data-label="Usia catatan">{{ $event->occurred_at->diffForHumans() }}</td><td data-label="Bukti">@if($event->evidence_path)<a href="{{ route('admin.loading-evidence.evidence', $event) }}" target="_blank" rel="noopener" class="btn-dt-action btn-dt-action--view">Lihat foto</a>@else<span>Tidak tersedia</span>@endif</td></tr>
      @empty
        <tr class="reconciliation-empty"><td colspan="5"><x-data-table-empty icon="check" title="Semua akses sudah ditutup" message="Tidak ada check-in yang menunggu check-out." /></td></tr>
      @endforelse
    </tbody></table></div>
  </section>

  <section class="admin-panel reconciliation-panel">
    <div class="admin-panel-header"><div><h2>Aktivitas di luar waktu izin</h2><p>Aktivitas terjadi di luar tanggal surat atau di antara pukul 11.00 dan 22.00 WITA.</p></div><span class="admin-status admin-status--rejected">{{ $summary['outside_window'] }} temuan</span></div>
    <div class="admin-table-wrap reconciliation-table-wrap"><table class="admin-table reconciliation-table"><thead><tr><th>Aktivitas</th><th>Surat / tenant</th><th>Jadwal surat</th><th>Petugas</th><th>Bukti</th></tr></thead><tbody>
      @forelse($outsideWindowEvents as $event)
        <tr><td data-label="Aktivitas"><strong>{{ $event->event === 'check_in' ? 'Check-in' : 'Check-out' }}</strong><span>{{ $event->occurred_at->format('d M Y, H:i:s') }} WITA</span></td><td data-label="Surat / tenant"><strong>{{ $event->loadingPermit?->permit_number ?? 'Surat tidak tersedia' }}</strong><span>{{ $event->loadingPermit?->tenant_name ?? 'Referensi #'.$event->permit_id }}</span></td><td data-label="Jadwal surat">@if($event->loadingPermit){{ $event->loadingPermit->start_date->format('d M') }}–{{ $event->loadingPermit->end_date->format('d M Y') }}<span>{{ $event->loadingPermit->movement_time_label }}</span>@else-@endif</td><td data-label="Petugas"><strong>{{ $event->securityOfficer?->name ?? 'Tidak tercatat' }}</strong><span>{{ $event->securityOfficer?->officer_id ?? '-' }}</span></td><td data-label="Bukti">@if($event->evidence_path)<a href="{{ route('admin.loading-evidence.evidence', $event) }}" target="_blank" rel="noopener" class="btn-dt-action btn-dt-action--view">Lihat foto</a>@else<span>Tidak tersedia</span>@endif</td></tr>
      @empty
        <tr class="reconciliation-empty"><td colspan="5"><x-data-table-empty icon="check" title="Tidak ada aktivitas di luar waktu" message="Catatan gerbang dalam rentang sesuai tanggal dan jam operasional loading." /></td></tr>
      @endforelse
    </tbody></table></div>
  </section>
</div>
@endsection
