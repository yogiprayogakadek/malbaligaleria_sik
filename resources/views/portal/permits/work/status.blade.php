@extends('layouts.portal')

@section('title', 'Status ' . $permit->permit_number . ' : Mal Bali Galeria')
@section('page-title', 'Status Surat Izin Kerja')

@section('content')
<div class="page-wrap page-wrap--narrow">
  <div class="page-header">
    <div>
      <div class="page-breadcrumb"><a href="{{ route('portal.dashboard') }}">Beranda</a><svg width="14" height="14"><use href="#i-chevron-right"/></svg><span>Status Izin Kerja</span></div>
      <h1 class="page-title">{{ $permit->permit_number }}</h1>
      <p class="page-subtitle">{{ $permit->contractor_name }} · {{ $permit->work_location }}</p>
    </div>
    @if(in_array($permit->status, ['approved', 'completed', 'refund_processing', 'refunded'], true))
      <div class="page-actions">
        <a href="{{ route('work-permits.letter.preview', $permit->applicant_token) }}" class="btn-primary">
          <svg><use href="#i-file"/></svg>
          Lihat Surat Izin
        </a>
      </div>
    @endif
  </div>

  @if(session('success'))<div class="permit-alert permit-alert--success" role="alert"><svg><use href="#i-check"/></svg><span>{{ session('success') }}</span></div>@endif
  @if($errors->any())<div class="permit-alert permit-alert--error" role="alert"><svg><use href="#i-info"/></svg><div><strong>Upload belum berhasil:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

  <section class="detail-card">
    <div class="work-status-heading"><div><span class="sdl">Status Saat Ini</span><h2>{{ $permit->status_label }}</h2></div><span class="badge-status badge-status--{{ $permit->status }}">{{ $permit->status_label }}</span></div>
    <dl class="detail-dl">
      <dt>Penanggung Jawab</dt><dd>{{ $permit->applicant_name }}</dd>
      <dt>Kategori</dt><dd>{{ $permit->work_category_label }}</dd>
      <dt>Uraian Pekerjaan</dt><dd>{{ $permit->work_type }}</dd>
      <dt>Divisi Pemeriksa</dt><dd>{{ $permit->assigned_division }}</dd>
      <dt>Periode</dt><dd>{{ $permit->start_date->format('d M Y') }} s.d. {{ $permit->end_date->format('d M Y') }}</dd>
      @if($permit->assigned_division === 'MEP')
        <dt>Security Deposit</dt><dd>{{ $permit->deposit_required === null ? 'Belum ditentukan MEP' : ($permit->deposit_required ? 'Diperlukan' : 'Tidak diperlukan') }}</dd>
      @endif
      @if($permit->assigned_division === 'MEP' && $permit->deposit_required)
        <dt>Nominal Deposit</dt><dd><strong>Rp {{ number_format((float) $permit->deposit_amount, 0, ',', '.') }}</strong></dd>
        <dt>Catatan MEP</dt><dd>{{ $permit->deposit_notes ?: '-' }}</dd>
      @endif
      @if($permit->finance_notes)<dt>Catatan Finance</dt><dd>{{ $permit->finance_notes }}</dd>@endif
      @if($permit->refund_amount)<dt>Nominal Dikembalikan</dt><dd><strong>Rp {{ number_format((float) $permit->refund_amount, 0, ',', '.') }}</strong></dd>@endif
    </dl>
  </section>

  @if(in_array($permit->status, ['awaiting_payment', 'payment_revision'], true))
    <section class="detail-card work-flow-action">
      <h2 class="detail-card-title">Upload Bukti Pembayaran</h2>
      <p class="page-subtitle">Transfer sesuai nominal yang ditetapkan MEP. Finance akan memeriksa apakah dana benar-benar telah masuk.</p>
      <form action="{{ route('work-permits.payment-proof.store', $permit->applicant_token) }}" method="POST" enctype="multipart/form-data" class="tr-decision-form">
        @csrf
        <div class="pf"><label for="payment_proof">Bukti Transfer <span class="req">*</span></label><input id="payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,application/pdf" required><span class="pf-hint">JPG, PNG, atau PDF; maksimal 5 MB.</span></div>
        <button type="submit" class="btn-primary"><svg><use href="#i-file"/></svg>{{ $permit->status === 'payment_revision' ? 'Upload Ulang Bukti' : 'Kirim Bukti ke Finance' }}</button>
      </form>
    </section>
  @elseif($permit->status === 'payment_review')
    <div class="permit-alert permit-alert--info"><svg><use href="#i-clock"/></svg><span>Bukti pembayaran telah diterima dan sedang diverifikasi oleh Finance.</span></div>
  @endif

  @if($permit->status === 'refunded' && $permit->refund_proof_path)
    <section class="detail-card work-flow-action"><h2 class="detail-card-title">Pengembalian Deposit</h2><p class="page-subtitle">MEP telah mencatat pengembalian security deposit.</p><a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('work-permits.refund-proof.preview', now()->addMinutes(10), ['token' => $permit->applicant_token]) }}" class="btn-secondary"><svg><use href="#i-file"/></svg>Lihat Bukti Pengembalian</a></section>
  @endif

  <section class="detail-card">
    <h2 class="detail-card-title">Riwayat Status</h2>
    <ol class="work-status-timeline">
      @forelse($permit->statusLogs as $log)
        <li><span>{{ $log->created_at->format('d M Y, H:i') }}</span><strong>{{ ucfirst(str_replace('_', ' ', $log->action)) }}</strong>@if($log->notes)<p>{{ $log->notes }}</p>@endif</li>
      @empty
        <li><span>{{ $permit->created_at->format('d M Y, H:i') }}</span><strong>Permohonan diajukan</strong></li>
      @endforelse
    </ol>
  </section>
</div>
@endsection
