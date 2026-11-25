@extends('layouts.validator')

@section('title', 'Dashboard Security : Mal Bali Galeria')
@section('page-title', 'Dashboard Security')

@section('content')
<div class="page-wrap security-dashboard-page">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><span>Security</span><svg><use href="#i-chevron-right"/></svg><span>{{ $status === 'today' ? 'Hari ini' : 'Semua tervalidasi' }}</span></div>
      <h1 class="page-title">{{ $status === 'today' ? 'Surat berlaku hari ini' : 'Semua surat tervalidasi' }}</h1>
      <p class="page-subtitle">{{ $status === 'today' ? 'Pantau izin yang dapat digunakan hari ini.' : 'Akses surat resmi yang telah disetujui dan tidak dicabut.' }}</p>
    </div>
    <a href="{{ route('scanner.index') }}" class="btn-primary"><svg><use href="#i-scan"/></svg>Buka scanner</a>
  </div>

  <section class="validator-metrics">
    <a href="{{ route('security.index', ['status' => 'all']) }}" class="validator-metric validator-metric--all"><span class="validator-metric-icon"><svg><use href="#i-file"/></svg></span><span class="validator-metric-copy"><span>Semua tervalidasi</span><strong>{{ $validatedPermitCount }}</strong></span></a>
    <a href="{{ route('security.index', ['status' => 'today']) }}" class="validator-metric validator-metric--approved"><span class="validator-metric-icon"><svg><use href="#i-clock"/></svg></span><span class="validator-metric-copy"><span>Berlaku hari ini</span><strong>{{ $todayPermitCount }}</strong></span></a>
    <div class="validator-metric"><span class="validator-metric-icon"><svg><use href="#i-arrow-right"/></svg></span><span class="validator-metric-copy"><span>Check-in hari ini</span><strong>{{ $checkedInCount }}</strong></span></div>
    <div class="validator-metric"><span class="validator-metric-icon"><svg><use href="#i-check"/></svg></span><span class="validator-metric-copy"><span>Check-out hari ini</span><strong>{{ $checkedOutCount }}</strong></span></div>
  </section>

  <div class="dt-card security-permit-table">
    <div class="security-permit-heading">
      <div><h2>{{ $status === 'today' ? 'Surat Berlaku Hari Ini' : 'Arsip Surat Tervalidasi' }}</h2><span>{{ $permits->total() }} surat ditemukan</span></div>
      <form method="GET" action="{{ route('security.index') }}" class="security-permit-filters">
        <input type="hidden" name="status" value="{{ $status }}">
        <label for="securityPermitSearch">Cari surat</label>
        <div>
          <input id="securityPermitSearch" type="search" name="q" value="{{ $query }}" maxlength="100" placeholder="Nomor surat atau tenant">
          <select name="per_page" aria-label="Jumlah surat per halaman">
            @foreach([10, 20, 50] as $option)<option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} data</option>@endforeach
          </select>
          <button type="submit" class="btn-dt-action btn-dt-action--primary">Cari</button>
          @if($query !== '')<a href="{{ route('security.index', ['status' => $status, 'per_page' => $perPage]) }}" class="btn-dt-action btn-dt-action--view">Reset</a>@endif
        </div>
      </form>
    </div>

    @if($permits->isEmpty())
      <x-data-table-empty icon="search" title="Surat tidak ditemukan" message="Ubah kata kunci atau pilih daftar surat lainnya." />
    @else
      <div class="dt-table-responsive">
        <table class="dt-table">
          <thead><tr><th>JENIS</th><th>NOMOR SURAT</th><th>TENANT / KONTRAKTOR</th><th>PERIODE</th><th>STATUS</th><th>AKSI</th></tr></thead>
          <tbody>
            @foreach($permits as $permit)
              @php
                $typeLabel = match($permit->permit_type) { 'loading' => 'Loading / Unloading', 'event' => 'Surat Izin Event', default => 'Surat Izin Kerja' };
                $downloadUrl = $permit->permit_type === 'loading' ? route('security.permits.loading.letter', $permit->id) : route('security.permits.work.letter', $permit->id);
                $statusClass = in_array($permit->status_label, ['Aktif', 'Selesai', 'Pekerjaan Selesai', 'Deposit Dikembalikan'], true) ? 'dt-status-approved' : ($permit->status_label === 'Kedaluwarsa' ? 'dt-status-rejected' : 'dt-status-pending');
              @endphp
              <tr class="dt-row">
                <td><span class="dt-badge-role role-{{ $permit->permit_type === 'loading' ? 'loading' : 'work' }}">{{ $typeLabel }}</span></td>
                <td><span class="dt-mono-ref">{{ $permit->permit_number }}</span></td>
                <td><span class="dt-user-name">{{ $permit->entity_name }}</span></td>
                <td><span class="dt-period">{{ \Carbon\Carbon::parse($permit->start_date)->format('d M') }} s.d. {{ \Carbon\Carbon::parse($permit->end_date)->format('d M Y') }}</span></td>
                <td><span class="dt-badge-status {{ $statusClass }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span></td>
                <td><div class="security-permit-actions">
                  @if($permit->can_verify && $permit->scan_token)<a href="{{ route('scanner.index', ['token' => $permit->scan_token]) }}" class="btn-dt-action btn-dt-action--primary">Verifikasi</a>@endif
                  <a href="{{ $downloadUrl }}" class="btn-dt-action btn-dt-action--view">Unduh surat</a>
                </div></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="dt-mobile-list">
        @foreach($permits as $permit)
          @php
            $typeLabel = match($permit->permit_type) { 'loading' => 'Loading / Unloading', 'event' => 'Surat Izin Event', default => 'Surat Izin Kerja' };
            $downloadUrl = $permit->permit_type === 'loading' ? route('security.permits.loading.letter', $permit->id) : route('security.permits.work.letter', $permit->id);
          @endphp
          <article class="dt-mobile-card security-permit-card">
            <div class="dt-mobile-card-header"><div class="dt-mobile-card-main"><div class="dt-mobile-card-title-row"><span class="dt-mobile-card-title">{{ $permit->entity_name }}</span><span class="dt-badge-role role-{{ $permit->permit_type === 'loading' ? 'loading' : 'work' }}">{{ $typeLabel }}</span></div><div class="dt-mobile-card-meta"><span class="dt-mono-ref">{{ $permit->permit_number }}</span><span>{{ $permit->status_label }}</span></div></div></div>
            <dl class="dt-mobile-detail-grid"><div class="dt-mobile-detail-row"><dt>Periode</dt><dd>{{ \Carbon\Carbon::parse($permit->start_date)->format('d M') }} s.d. {{ \Carbon\Carbon::parse($permit->end_date)->format('d M Y') }}</dd></div></dl>
            <div class="dt-mobile-card-footer security-permit-actions">
              @if($permit->can_verify && $permit->scan_token)<a href="{{ route('scanner.index', ['token' => $permit->scan_token]) }}" class="btn-dt-action btn-dt-action--primary">Verifikasi surat</a>@endif
              <a href="{{ $downloadUrl }}" class="btn-dt-action btn-dt-action--view">Unduh surat</a>
            </div>
          </article>
        @endforeach
      </div>

      <div class="security-permit-pagination">
        <span>Menampilkan {{ $permits->firstItem() }}–{{ $permits->lastItem() }} dari {{ $permits->total() }} surat</span>
        <nav class="dt-pagination-nav" aria-label="Navigasi halaman surat">
          @if($permits->onFirstPage())<span class="dt-page-btn disabled" aria-disabled="true">Sebelumnya</span>@else<a href="{{ $permits->previousPageUrl() }}" class="dt-page-btn" rel="prev">Sebelumnya</a>@endif
          <span class="dt-page-btn active" aria-current="page">{{ $permits->currentPage() }} / {{ $permits->lastPage() }}</span>
          @if($permits->hasMorePages())<a href="{{ $permits->nextPageUrl() }}" class="dt-page-btn" rel="next">Berikutnya</a>@else<span class="dt-page-btn disabled" aria-disabled="true">Berikutnya</span>@endif
        </nav>
      </div>
    @endif
  </div>

  <section class="security-gate-panel">
    <div class="security-gate-header"><h2>Aktivitas gerbang terbaru</h2><p>Riwayat bukti masuk dan keluar dari scanner.</p></div>
    <div class="security-gate-list">
      @forelse($gateEvents as $event)
        <article><span class="security-gate-status security-gate-status--{{ $event->event === 'check_in' ? 'in' : 'out' }}">{{ $event->event === 'check_in' ? 'Masuk' : 'Keluar' }}</span><strong>{{ $event->display_reference ?: 'Surat tidak tersedia' }}</strong><time>{{ $event->occurred_at->format('d M Y, H:i') }} WITA</time></article>
      @empty
        <div class="security-gate-empty"><strong>Belum ada aktivitas</strong><span>Bukti masuk dan keluar akan tercatat di sini.</span></div>
      @endforelse
    </div>
  </section>
</div>
@endsection
