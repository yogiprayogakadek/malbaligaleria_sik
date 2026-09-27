@extends('layouts.portal')

@section('title', 'Review ' . $permit->permit_number . ' : Dashboard TR')
@section('page-title', 'Review Permohonan')

@section('content')
<div class="page-wrap">

  <div class="page-header">
    <div>
      <div class="page-breadcrumb">
        <a href="{{ route('tr.index') }}">Dashboard TR</a>
        <svg width="14" height="14"><use href="#i-chevron-right"/></svg>
        <span>Review Permohonan</span>
      </div>
      <h1 class="page-title">{{ $permit->permit_number }}</h1>
    </div>
  </div>

  @if($permit->status !== 'pending')
    <div class="permit-alert {{ $permit->status === 'approved' ? 'permit-alert--success' : 'permit-alert--error' }}" role="alert">
      <svg><use href="#i-{{ $permit->status === 'approved' ? 'check' : 'info' }}"/></svg>
      <span>
        Permohonan ini sudah <strong>{{ $permit->status_label }}</strong> pada
        {{ $permit->reviewed_at?->format('d M Y, H:i') }} WITA
        oleh {{ $permit->reviewer?->name ?? 'Validator TR' }}.
        @if($permit->review_notes) Catatan: {{ $permit->review_notes }} @endif
      </span>
    </div>
  @endif

  <div class="tr-review-grid">

    {{-- Detail Permohonan --}}
    <div class="detail-card">
      <h2 class="detail-card-title">Detail Permohonan</h2>
      <dl class="detail-dl">
        <dt>Nomor Surat</dt><dd><strong>{{ $permit->permit_number }}</strong></dd>
        <dt>Tenant</dt><dd>{{ $permit->tenant_name }}</dd>
        <dt>PIC</dt><dd>{{ $permit->applicant_name }}</dd>
        <dt>HP PIC</dt><dd>{{ $permit->applicant_phone }}</dd>
        @if($permit->applicant_email)
          <dt>Email PIC</dt><dd>{{ $permit->applicant_email }}</dd>
        @endif
        <dt>Arah Loading</dt>
        <dd><span class="dir-badge dir-badge--{{ $permit->direction }}">{{ $permit->direction_label }}</span></dd>
        <dt>Tanggal Mulai</dt><dd>{{ $permit->start_date->format('d F Y') }}</dd>
        <dt>Tanggal Selesai</dt><dd>{{ $permit->end_date->format('d F Y') }}</dd>
        <dt>Jumlah Barang</dt><dd>{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</dd>
        <dt>Keterangan Barang</dt><dd>{{ $permit->item_description ?: '-' }}</dd>
        @if($permit->vehicle_plate)
          <dt>No. Kendaraan</dt><dd>{{ $permit->vehicle_plate }}</dd>
        @endif
        <dt>Diajukan</dt><dd>{{ $permit->created_at->format('d F Y, H:i') }} WITA</dd>
        <dt>Jenis Dokumen ID</dt><dd>{{ strtoupper($permit->id_doc_type) }}</dd>
      </dl>

      {{-- Dokumen KTP/SIM --}}
      <div class="tr-id-doc-preview">
        <div class="tr-id-doc-label">Dokumen Identitas ({{ strtoupper($permit->id_doc_type) }})</div>
        <a href="{{ route('tr.id-doc', $permit->permit_number) }}" target="_blank"
           class="tr-id-doc-link">
          <svg><use href="#i-shield-check"/></svg>
          Lihat Dokumen Identitas Terlampir
        </a>
      </div>
    </div>

    {{-- Panel Keputusan --}}
    @if($permit->status === 'pending')
    <div class="tr-decision-panel">
      <h2>Keputusan Verifikasi</h2>
      <p class="tr-decision-note">
        Periksa kelengkapan data dan dokumen identitas pemohon sebelum membuat keputusan.
      </p>

      {{-- Approve --}}
      <form action="{{ route('tr.approve', $permit->permit_number) }}" method="POST"
            id="approveForm" class="tr-decision-form">
        @csrf
        <div class="pf">
          <label for="approve_notes">Catatan Persetujuan <small>(opsional)</small></label>
          <textarea id="approve_notes" name="review_notes" rows="3"
                    placeholder="Contoh: Dokumen lengkap, data valid. Disetujui."></textarea>
        </div>
        <button type="submit" class="btn-tr-approve" id="approveBtn">
          <svg><use href="#i-check"/></svg>
          Setujui Permohonan
        </button>
      </form>

      <div class="tr-decision-divider">atau</div>

      {{-- Reject --}}
      <form action="{{ route('tr.reject', $permit->permit_number) }}" method="POST"
            id="rejectForm" class="tr-decision-form">
        @csrf
        <div class="pf">
          <label for="reject_notes">Alasan Penolakan <span class="req">*</span></label>
          <textarea id="reject_notes" name="review_notes" rows="3" required minlength="10"
                    placeholder="Wajib diisi. Contoh: Foto dokumen identitas tidak jelas atau masa berlaku sudah habis."></textarea>
          @error('review_notes')
            <span class="pf-error">{{ $message }}</span>
          @enderror
        </div>
        <button type="submit" class="btn-tr-reject" id="rejectBtn">
          <svg><use href="#i-trash"/></svg>
          Tolak Permohonan
        </button>
      </form>
    </div>
    @else
    <div class="tr-decision-panel">
      <h2>Hasil Verifikasi</h2>
      <div class="tr-verdict {{ $permit->status === 'approved' ? 'tr-verdict--approved' : 'tr-verdict--rejected' }}">
        <svg width="24" height="24"><use href="#i-{{ $permit->status === 'approved' ? 'check' : 'info' }}"/></svg>
        <strong>{{ $permit->status_label }}</strong>
      </div>
      @if($permit->review_notes)
        <div class="tr-verdict-notes">{{ $permit->review_notes }}</div>
      @endif
      @if($permit->status === 'approved')
        <a href="{{ route('loading.letter', $permit->permit_number) }}" target="_blank" class="btn-primary" style="margin-top:16px; display:inline-flex; gap:8px; align-items:center;">
          <svg><use href="#i-file"/></svg>
          Lihat Surat Izin Resmi
        </a>
      @endif
    </div>
    @endif

  </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('approveForm')?.addEventListener('submit', e => {
  if (!confirm('Setujui permohonan ini? Barcode token surat akan diterbitkan dan notifikasi dikirimkan ke tenant.')) {
    e.preventDefault();
  }
});
document.getElementById('rejectForm')?.addEventListener('submit', e => {
  const notes = document.getElementById('reject_notes')?.value.trim();
  if (!notes || notes.length < 10) {
    e.preventDefault();
    document.getElementById('reject_notes')?.focus();
    if (window.showToast) window.showToast('Alasan penolakan wajib diisi minimal 10 karakter.');
    else alert('Alasan penolakan wajib diisi minimal 10 karakter.');
    return;
  }
  if (!confirm('Tolak permohonan ini? Keputusan ini akan dicatat dan dikirimkan ke tenant.')) {
    e.preventDefault();
  }
});
</script>
@endpush
