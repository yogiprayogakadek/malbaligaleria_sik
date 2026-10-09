@extends('layouts.validator')

@section('title', 'Verifikasi Security Deposit : Finance : Mal Bali Galeria')
@section('page-title', 'Dashboard Finance')

@section('content')
<div class="page-wrap">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><span>Finance</span><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Security Deposit</span></div>
      <h1 class="page-title">Verifikasi security deposit</h1>
      <p class="page-subtitle">Cocokkan bukti transfer dengan dana yang masuk. Nominal deposit ditetapkan oleh MEP.</p>
    </div>
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif

  <div class="dt-card" id="validatorPermitResults" data-validator-queue-status="{{ $status }}">
    <div class="dt-toolbar"><div class="dt-header-left"><h2 class="dt-title">Daftar Deposit</h2><span class="dt-count">&middot; {{ $permits->total() }} data</span></div></div>
    @if($permits->isEmpty())
      <x-data-table-empty
        icon="check"
        title="Belum ada pembayaran"
        message="Bukti pembayaran yang perlu diperiksa akan muncul di sini."
      />
    @else
      <div class="dt-table-responsive">
        <table class="dt-table">
          <thead><tr><th>PERMOHONAN</th><th>PEMOHON</th><th>NOMINAL</th><th>BUKTI DIKIRIM</th><th>STATUS</th><th>TINDAKAN</th></tr></thead>
          <tbody>
          @foreach($permits as $permit)
            <tr class="dt-row">
              <td><span class="dt-mono-ref">{{ $permit->permit_number }}</span><div class="dt-user-sub">{{ $permit->work_type }}</div></td>
              <td><span class="dt-user-name">{{ $permit->contractor_name }}</span><div class="dt-user-sub">{{ $permit->applicant_name }}</div></td>
              <td><strong>Rp {{ number_format((float) $permit->deposit_amount, 0, ',', '.') }}</strong></td>
              <td>{{ $permit->payment_submitted_at?->format('d M Y, H:i') ?? '-' }}</td>
              <td><span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span></td>
              <td><a href="{{ route('staff.work-permits.show', $permit->public_token) }}" class="btn-dt-action {{ $permit->status === 'payment_review' ? 'btn-dt-action--primary' : 'btn-dt-action--view' }}">{{ $permit->status === 'payment_review' ? 'Verifikasi' : 'Lihat' }}</a></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      <div class="dt-mobile-list">
        @foreach($permits as $permit)
          <article class="dt-mobile-card">
            <div class="dt-mobile-card-header"><div class="dt-mobile-card-main"><div class="dt-mobile-card-title-row"><span class="dt-mobile-card-title">{{ $permit->contractor_name }}</span><span class="dt-badge-status dt-status-{{ $permit->status }}"><span class="dt-dot"></span>{{ $permit->status_label }}</span></div><div class="dt-mobile-card-meta"><span class="dt-mono-ref">{{ $permit->permit_number }}</span><strong>Rp {{ number_format((float) $permit->deposit_amount, 0, ',', '.') }}</strong></div></div></div>
            <div class="dt-mobile-card-footer"><a href="{{ route('staff.work-permits.show', $permit->public_token) }}" class="btn-dt-action btn-dt-action--primary">Buka Deposit</a></div>
          </article>
        @endforeach
      </div>
      <div class="dt-footer">{{ $permits->links() }}</div>
    @endif
  </div>
</div>
@endsection
