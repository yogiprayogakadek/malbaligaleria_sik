@extends('layouts.validator')

@section('title', 'Dashboard Security : Mal Bali Galeria')
@section('page-title', 'Dashboard Security')

@section('content')
<div class="page-wrap security-dashboard-page">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><span>Security</span><svg><use href="#i-chevron-right"/></svg><span>Akses hari ini</span></div>
      <h1 class="page-title">Surat berlaku hari ini</h1>
      <p class="page-subtitle">Pantau izin aktif dan catatan masuk-keluar dari scanner QR.</p>
    </div>
    <a href="{{ route('scanner.index') }}" class="btn-primary"><svg><use href="#i-scan"/></svg>Buka scanner</a>
  </div>

  <section class="validator-metrics">
    <div class="validator-metric"><span class="validator-metric-icon"><svg><use href="#i-file"/></svg></span><span class="validator-metric-copy"><span>Izin aktif</span><strong>{{ $loadingPermits->count() + $workPermits->count() }}</strong></span></div>
    <div class="validator-metric"><span class="validator-metric-icon"><svg><use href="#i-arrow-right"/></svg></span><span class="validator-metric-copy"><span>Check-in hari ini</span><strong>{{ $checkedInCount }}</strong></span></div>
    <div class="validator-metric"><span class="validator-metric-icon"><svg><use href="#i-check"/></svg></span><span class="validator-metric-copy"><span>Check-out hari ini</span><strong>{{ $checkedOutCount }}</strong></span></div>
  </section>

  <div class="dt-card security-permit-table" data-staff-data-table data-total-records="{{ $loadingPermits->count() + $workPermits->count() }}">
    <x-staff-data-table-controls
      :total="$loadingPermits->count() + $workPermits->count()"
      :per-page-raw="'all'"
      placeholder="Cari nomor surat atau tenant..."
      label="Cari surat berlaku"
    />

    @if($loadingPermits->isEmpty() && $workPermits->isEmpty())
      <x-data-table-empty icon="scan" title="Tidak ada surat aktif" message="Surat yang berlaku hari ini akan muncul di sini." />
    @else
      <div class="dt-table-responsive">
        <table class="dt-table">
          <thead><tr><th>JENIS</th><th>NOMOR SURAT</th><th>TENANT / KONTRAKTOR</th><th>PERIODE</th><th>AKSI</th></tr></thead>
          <tbody>
            @foreach($loadingPermits as $permit)
              <tr class="dt-row" data-staff-record data-search="{{ strtolower($permit->permit_number.' '.$permit->tenant_name.' loading unloading') }}">
                <td><span class="dt-badge-role role-loading">Loading / Unloading</span></td>
                <td><span class="dt-mono-ref">{{ $permit->permit_number }}</span></td>
                <td><span class="dt-user-name">{{ $permit->tenant_name }}</span></td>
                <td><span class="dt-period">{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</span></td>
                <td><a href="{{ route('scanner.index', ['token' => $permit->barcode_token]) }}" class="btn-dt-action btn-dt-action--primary">Verifikasi</a></td>
              </tr>
            @endforeach
            @foreach($workPermits as $permit)
              <tr class="dt-row" data-staff-record data-search="{{ strtolower($permit->permit_number.' '.$permit->contractor_name.' surat izin kerja') }}">
                <td><span class="dt-badge-role role-work">Surat Izin Kerja</span></td>
                <td><span class="dt-mono-ref">{{ $permit->permit_number }}</span></td>
                <td><span class="dt-user-name">{{ $permit->contractor_name }}</span><div class="dt-user-sub">{{ $permit->workers_count }} pekerja</div></td>
                <td><span class="dt-period">{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</span></td>
                <td><a href="{{ route('scanner.index', ['token' => $permit->public_token]) }}" class="btn-dt-action btn-dt-action--primary">Verifikasi</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="dt-mobile-list">
        @foreach($loadingPermits as $permit)
          <article class="dt-mobile-card security-permit-card" data-staff-record data-search="{{ strtolower($permit->permit_number.' '.$permit->tenant_name.' loading unloading') }}">
            <div class="dt-mobile-card-header"><div class="dt-mobile-card-main"><div class="dt-mobile-card-title-row"><span class="dt-mobile-card-title">{{ $permit->tenant_name }}</span><span class="dt-badge-role role-loading">Loading / Unloading</span></div><div class="dt-mobile-card-meta"><span class="dt-mono-ref">{{ $permit->permit_number }}</span></div></div></div>
            <dl class="dt-mobile-detail-grid"><div class="dt-mobile-detail-row"><dt>Periode</dt><dd>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd></div></dl>
            <div class="dt-mobile-card-footer"><a href="{{ route('scanner.index', ['token' => $permit->barcode_token]) }}" class="btn-dt-action btn-dt-action--primary">Verifikasi surat</a></div>
          </article>
        @endforeach
        @foreach($workPermits as $permit)
          <article class="dt-mobile-card security-permit-card" data-staff-record data-search="{{ strtolower($permit->permit_number.' '.$permit->contractor_name.' surat izin kerja') }}">
            <div class="dt-mobile-card-header"><div class="dt-mobile-card-main"><div class="dt-mobile-card-title-row"><span class="dt-mobile-card-title">{{ $permit->contractor_name }}</span><span class="dt-badge-role role-work">Surat Izin Kerja</span></div><div class="dt-mobile-card-meta"><span class="dt-mono-ref">{{ $permit->permit_number }}</span><span>{{ $permit->workers_count }} pekerja</span></div></div></div>
            <dl class="dt-mobile-detail-grid"><div class="dt-mobile-detail-row"><dt>Periode</dt><dd>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd></div></dl>
            <div class="dt-mobile-card-footer"><a href="{{ route('scanner.index', ['token' => $permit->public_token]) }}" class="btn-dt-action btn-dt-action--primary">Verifikasi surat</a></div>
          </article>
        @endforeach
      </div>

      <div data-staff-filter-empty hidden><x-data-table-empty icon="search" title="Surat tidak ditemukan" message="Ubah nomor surat atau nama tenant yang dicari." /></div>
    @endif
  </div>

  <section class="security-gate-panel">
    <div class="security-gate-header"><h2>Aktivitas gerbang terbaru</h2><p>Riwayat check-in dan check-out dari scanner.</p></div>
    <div class="security-gate-list">
      @forelse($gateEvents as $event)
        <article><span class="security-gate-status security-gate-status--{{ $event->event === 'check_in' ? 'in' : 'out' }}">{{ $event->event === 'check_in' ? 'Masuk' : 'Keluar' }}</span><strong>{{ $event->display_reference ?: 'Surat tidak tersedia' }}</strong><time>{{ $event->occurred_at->format('d M Y, H:i') }} WITA</time></article>
      @empty
        <div class="security-gate-empty"><strong>Belum ada aktivitas</strong><span>Check-in dan check-out akan tercatat di sini.</span></div>
      @endforelse
    </div>
  </section>
</div>
@endsection
