@extends('layouts.validator')

@section('title', 'Surat Izin Kerja : Dashboard EP : Mal Bali Galeria')
@section('page-title', 'Dashboard Validator EP')

@section('content')
<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><span>Event &amp; Promotion</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Surat Izin Kerja</span></div>
      <h1 class="page-title">Antrean KOL Event</h1>
      <p class="page-subtitle">Periksa rincian kegiatan, pekerja, jadwal, dan dokumen pendukung KOL Event.</p>
    </div>
  </div>

  @if($status === 'all')
    <section class="validator-metrics" aria-label="Ringkasan permohonan KOL Event">
      @foreach(['all' => ['Total permohonan', 'file'], 'ep_review' => ['Perlu diperiksa', 'clock'], 'approved' => ['Disetujui', 'check'], 'rejected' => ['Ditolak', 'info']] as $key => [$label, $icon])
        <a href="{{ route('ep.index', ['status' => $key]) }}" class="validator-metric validator-metric--{{ $key }}"><span class="validator-metric-icon"><svg><use href="#i-{{ $icon }}"/></svg></span><span class="validator-metric-copy"><span>{{ $label }}</span><strong>{{ $counts[$key] }}</strong></span></a>
      @endforeach
    </section>
  @endif

  @if(session('success'))<div class="permit-alert permit-alert--success" role="status"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if(session('info'))<div class="permit-alert permit-alert--info" role="status"><svg><use href="#i-info"/></svg><span>{{ session('info') }}</span></div>@endif

  <div class="dt-card" id="validatorPermitResults" data-validator-queue-status="{{ $status }}">
    <div class="dt-toolbar"><div class="dt-header-left"><h2 class="dt-title">Daftar Permohonan</h2><span class="dt-count">&middot; {{ $permits->total() }} data</span></div><div class="dt-header-right"><div class="dt-search-wrap"><svg><use href="#i-search"/></svg><input type="search" id="workPermitSearch" class="dt-search-input" placeholder="Cari pemohon atau nomor..." aria-label="Cari permohonan KOL Event"></div></div></div>
    @if($permits->isEmpty())
      <x-data-table-empty icon="file" title="Belum ada KOL Event" message="Permohonan yang masuk ke divisi EP akan tampil di sini." />
    @else
      <div class="dt-table-responsive"><table class="dt-table"><thead><tr><th>KONTRAKTOR / PEMOHON</th><th>NOMOR PERMOHONAN</th><th>LOKASI</th><th>PERIODE</th><th>STATUS</th><th>TINDAKAN</th></tr></thead><tbody>
        @foreach($permits as $permit)
          <tr class="dt-row" data-search="{{ strtolower($permit->contractor_name.' '.$permit->applicant_name.' '.$permit->permit_number.' '.$permit->work_location) }}"><td><span class="dt-user-name">{{ $permit->contractor_name }}</span><div class="dt-user-sub">{{ $permit->applicant_name }} · {{ $permit->applicant_phone }}</div></td><td><span class="dt-mono-ref">{{ $permit->permit_number }}</span><div class="dt-user-sub">{{ $permit->workers_count }} pekerja</div></td><td>{{ $permit->work_location }}</td><td>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</td><td><span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span><x-sla-overdue :permit="$permit" /></td><td><a href="{{ route('staff.work-permits.show', $permit->public_token) }}" class="btn-dt-action {{ $permit->status === 'ep_review' ? 'btn-dt-action--primary' : 'btn-dt-action--view' }}">{{ $permit->status === 'ep_review' ? 'Periksa' : 'Lihat' }}</a></td></tr>
        @endforeach
      </tbody></table></div>
      <div class="dt-mobile-list">
        @foreach($permits as $permit)
          <article class="dt-mobile-card" data-search="{{ strtolower($permit->contractor_name.' '.$permit->applicant_name.' '.$permit->permit_number.' '.$permit->work_location) }}"><div class="dt-mobile-card-header"><div class="dt-mobile-card-main"><div class="dt-mobile-card-title-row"><span class="dt-mobile-card-title">{{ $permit->contractor_name }}</span><span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span><x-sla-overdue :permit="$permit" /></div><div class="dt-mobile-card-meta"><span class="dt-mono-ref">{{ $permit->permit_number }}</span><span>{{ $permit->work_location }}</span></div></div></div><div class="dt-mobile-detail-grid"><div class="dt-mobile-detail-row"><dt>Periode</dt><dd>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd></div></div><div class="dt-mobile-card-footer"><a href="{{ route('staff.work-permits.show', $permit->public_token) }}" class="btn-dt-action btn-dt-action--primary">Buka Permohonan</a></div></article>
        @endforeach
      </div>
      <div class="dt-footer">{{ $permits->links() }}</div>
    @endif
  </div>
</div>
@endsection
