@extends('layouts.admin')

@section('title', 'Detail Loading : Admin MBG')
@section('page-title', 'Detail Loading Barang')

@section('content')
<div class="page-wrap admin-page-wrap page-wrap--narrow">
  <a href="{{ route('admin.loading.index', ['status' => $permit->status]) }}" class="validator-back-link"><svg><use href="#i-arrow-left"/></svg>Kembali ke data loading</a>

  <div class="admin-detail-heading">
    <div><span class="admin-status admin-status--{{ $permit->status }}">{{ $permit->status_label }}</span><h1>{{ $permit->permit_number }}</h1><p>Diajukan {{ $permit->created_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }} WITA</p></div>
  </div>

  <section class="admin-panel admin-detail-section">
    <div class="admin-panel-header"><div><h2>Tenant dan pemohon</h2><p>Identitas pihak yang mengajukan izin.</p></div></div>
    <dl class="admin-detail-grid">
      <div><dt>Tenant</dt><dd>{{ $permit->tenant_name }}</dd></div>
      <div><dt>Nama pemohon</dt><dd>{{ $permit->applicant_name }}</dd></div>
      <div><dt>Nomor telepon</dt><dd>{{ $permit->applicant_phone }}</dd></div>
      <div><dt>Email</dt><dd>{{ $permit->applicant_email ?: '-' }}</dd></div>
    </dl>
  </section>

  <section class="admin-panel admin-detail-section">
    <div class="admin-panel-header"><div><h2>Rincian pergerakan barang</h2><p>Jadwal dan muatan yang diajukan.</p></div></div>
    <dl class="admin-detail-grid">
      <div><dt>Arah</dt><dd>{{ $permit->direction_label }}</dd></div>
      <div><dt>Periode</dt><dd>{{ $permit->start_date->format('d M Y') }} - {{ $permit->end_date->format('d M Y') }}</dd></div>
      <div><dt>{{ $permit->movement_time_field_label }}</dt><dd>{{ $permit->movement_time_label }}</dd></div>
      <div><dt>Jumlah</dt><dd>{{ $permit->item_count }} {{ $permit->item_unit }}</dd></div>
      <div><dt>Nomor kendaraan</dt><dd>{{ $permit->vehicle_plate ?: '-' }}</dd></div>
      <div class="admin-detail-wide"><dt>Deskripsi barang</dt><dd>{{ $permit->item_description }}</dd></div>
    </dl>
    <div class="admin-document-row">
      <span><strong>Dokumen identitas</strong><small>Tautan aman berlaku selama 5 menit.</small></span>
      <a href="{{ URL::temporarySignedRoute('tr.id-doc', now()->addMinutes(5), ['documentToken' => $permit->document_token]) }}" target="_blank" rel="noopener noreferrer" class="admin-secondary-button"><svg><use href="#i-eye"/></svg>Lihat dokumen</a>
    </div>
  </section>

  @if($permit->reviewed_at)
    <section class="admin-panel admin-detail-section">
      <div class="admin-panel-header"><div><h2>Hasil pemeriksaan</h2><p>Keputusan yang dicatat oleh validator.</p></div></div>
      <dl class="admin-detail-grid">
        <div><dt>Validator</dt><dd>{{ $permit->reviewer?->name ?? '-' }}</dd></div>
        <div><dt>Waktu pemeriksaan</dt><dd>{{ $permit->reviewed_at->timezone(config('operating-hours.timezone'))->format('d M Y, H:i') }} WITA</dd></div>
        <div class="admin-detail-wide"><dt>Catatan</dt><dd>{{ $permit->review_notes ?: 'Tidak ada catatan.' }}</dd></div>
      </dl>
    </section>
  @endif
</div>
@endsection
