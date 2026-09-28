@extends('layouts.validator')

@section('title', 'Surat Izin Kerja : Dashboard MEP : Mal Bali Galeria')
@section('page-title', 'Dashboard Validator MEP')

@section('content')
<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><span>MEP</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Surat Izin Kerja</span></div>
      <h1 class="page-title">Antrean Surat Izin Kerja</h1>
      <p class="page-subtitle">Periksa lokasi, jadwal, daftar pekerja, dan dokumen identitas yang diajukan.</p>
    </div>
  </div>

  @if($status === 'all')
    <section class="validator-metrics" aria-label="Ringkasan permohonan izin kerja">
      @foreach([
        'all' => ['Total permohonan', 'file'],
        'action' => ['Perlu tindakan', 'clock'],
        'approved' => ['Disetujui', 'check'],
        'refunded' => ['Deposit kembali', 'info'],
      ] as $key => [$label, $icon])
        <a href="{{ route('mep.index', ['status' => $key]) }}" class="validator-metric validator-metric--{{ $key }}">
          <span class="validator-metric-icon"><svg><use href="#i-{{ $icon }}"/></svg></span>
          <span class="validator-metric-copy"><span>{{ $label }}</span><strong>{{ $counts[$key] }}</strong></span>
        </a>
      @endforeach
    </section>
  @endif

  <div class="dt-card" id="validatorPermitResults" data-validator-queue-status="{{ $status }}">
    <div class="dt-toolbar">
      <div class="dt-header-left"><h2 class="dt-title">Daftar Permohonan</h2><span class="dt-count">&middot; {{ $permits->total() }} data</span></div>
      <div class="dt-header-right">
        <div class="dt-search-wrap"><svg><use href="#i-search"/></svg><input type="search" id="workPermitSearch" class="dt-search-input" placeholder="Cari kontraktor atau nomor..." aria-label="Cari permohonan izin kerja"></div>
      </div>
    </div>

    @if($permits->isEmpty())
      <div class="tr-empty"><svg width="40" height="40"><use href="#i-wrench"/></svg><p>Belum ada permohonan pada status ini.</p></div>
    @else
      <div class="dt-table-responsive">
        <table class="dt-table" id="workPermitTable">
          <thead><tr><th>KONTRAKTOR / PEMOHON</th><th>NOMOR PERMOHONAN</th><th>LOKASI</th><th>PERIODE</th><th>STATUS</th><th>TINDAKAN</th></tr></thead>
          <tbody>
            @foreach($permits as $permit)
              <tr class="dt-row" data-search="{{ strtolower($permit->contractor_name.' '.$permit->applicant_name.' '.$permit->permit_number.' '.$permit->work_location) }}">
                <td><div class="dt-user-info"><span class="dt-user-name">{{ $permit->contractor_name }}</span><span class="dt-user-sub">{{ $permit->applicant_name }} · {{ $permit->applicant_phone }}</span></div></td>
                <td><span class="dt-mono-ref">{{ $permit->permit_number }}</span><div class="dt-user-sub">{{ $permit->workers_count }} pekerja</div></td>
                <td>{{ $permit->work_location }}</td>
                <td>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</td>
                <td><span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span></td>
                <td><a href="{{ route('staff.work-permits.show', $permit->public_token) }}" class="btn-dt-action {{ in_array($permit->status, ['mep_review', 'mep_final_review', 'completed', 'refund_processing'], true) ? 'btn-dt-action--primary' : 'btn-dt-action--view' }}">{{ in_array($permit->status, ['mep_review', 'mep_final_review', 'completed', 'refund_processing'], true) ? 'Proses' : 'Lihat' }}</a></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="dt-mobile-list" id="workPermitMobileList">
        @foreach($permits as $permit)
          <article class="dt-mobile-card" data-search="{{ strtolower($permit->contractor_name.' '.$permit->applicant_name.' '.$permit->permit_number.' '.$permit->work_location) }}">
            <div class="dt-mobile-card-header">
              <div class="dt-mobile-card-main">
                <div class="dt-mobile-card-title-row"><span class="dt-mobile-card-title">{{ $permit->contractor_name }}</span><span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span></div>
                <div class="dt-mobile-card-meta"><span class="dt-mono-ref">{{ $permit->permit_number }}</span><span>{{ $permit->workers_count }} pekerja</span></div>
              </div>
            </div>
            <div class="dt-mobile-detail-grid">
              <div class="dt-mobile-detail-row"><dt>Lokasi</dt><dd>{{ $permit->work_location }}</dd></div>
              <div class="dt-mobile-detail-row"><dt>Periode</dt><dd>{{ $permit->start_date->format('d M') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd></div>
            </div>
            <div class="dt-mobile-card-footer"><a href="{{ route('staff.work-permits.show', $permit->public_token) }}" class="btn-dt-action btn-dt-action--primary">Buka Permohonan</a></div>
          </article>
        @endforeach
      </div>

      <div class="dt-footer">{{ $permits->links() }}</div>
    @endif
  </div>
</div>
@endsection
