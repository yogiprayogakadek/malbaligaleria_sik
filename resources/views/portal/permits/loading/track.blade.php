@extends('layouts.portal')

@section('title', 'Status Permohonan ' . $permit->permit_number . ' : Mal Bali Galeria')
@section('page-title', 'Status Permohonan')

@section('content')
<div class="page-wrap page-wrap--narrow">

  <div class="page-header">
    <div>
      <div class="page-breadcrumb">
        <a href="{{ route('portal.dashboard') }}">Beranda</a>
        <svg width="14" height="14"><use href="#i-chevron-right"/></svg>
        <a href="{{ route('portal.track') }}">Cek Status</a>
        <svg width="14" height="14"><use href="#i-chevron-right"/></svg>
        <span>Status Permohonan</span>
      </div>
      <h1 class="page-title">{{ $permit->permit_number }}</h1>
    </div>
    <div class="page-actions">
      @if($permit->status === 'approved')
        <a href="{{ route('loading.letter', $permit->permit_number) }}" class="btn-primary" target="_blank">
          <svg><use href="#i-file"/></svg>
          Unduh Surat Izin
        </a>
      @endif
    </div>
  </div>

  {{-- Status Banner --}}
  @php
    $bannerClass = match($permit->status) {
      'approved' => 'status-banner--approved',
      'rejected' => 'status-banner--rejected',
      default    => 'status-banner--pending',
    };
  @endphp
  <div class="status-banner {{ $bannerClass }}">
    <div class="status-banner-icon">
      <svg><use href="#i-{{ $permit->status === 'approved' ? 'check' : ($permit->status === 'rejected' ? 'info' : 'clock') }}"/></svg>
    </div>
    <div class="status-banner-content">
      <strong>Status: {{ $permit->status_label }}</strong>
      @if($permit->status === 'approved')
        <p>Permohonan disetujui pada {{ $permit->reviewed_at?->format('d M Y, H:i') }} WITA. Surat izin resmi telah diterbitkan.</p>
      @elseif($permit->status === 'rejected')
        <p>Permohonan ditolak oleh tim verifikasi. Alasan: {{ $permit->review_notes }}</p>
      @else
        <p>Permohonan sedang dalam antrian verifikasi operasional divisi TR (Tenant Relationship).</p>
      @endif
    </div>
  </div>

  {{-- Detail Permohonan --}}
  <div class="detail-card">
    <div class="detail-card-header">
      <h2 class="detail-card-title">Detail Permohonan</h2>
      <span class="badge-status badge-status--{{ $permit->status }}">{{ $permit->status_label }}</span>
    </div>

    <dl class="detail-dl">
      <dt>Nomor Surat</dt><dd><strong>{{ $permit->permit_number }}</strong></dd>
      <dt>Nama Tenant</dt><dd>{{ $permit->tenant_name }}</dd>
      <dt>Penanggung Jawab (PIC)</dt><dd>{{ $permit->applicant_name }} ({{ $permit->applicant_phone }})</dd>
      <dt>Arah Loading</dt><dd>{{ $permit->direction_label }}</dd>
      <dt>Tanggal Mulai</dt><dd>{{ $permit->start_date->format('d M Y') }}</dd>
      <dt>Tanggal Selesai</dt><dd>{{ $permit->end_date->format('d M Y') }}</dd>
      <dt>Jumlah Barang</dt><dd>{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</dd>
      <dt>Keterangan</dt><dd>{{ $permit->item_description ?: '-' }}</dd>
      @if($permit->vehicle_plate)
        <dt>No. Kendaraan</dt><dd>{{ $permit->vehicle_plate }}</dd>
      @endif
      <dt>Diajukan</dt><dd>{{ $permit->created_at->format('d M Y, H:i') }} WITA</dd>
    </dl>

    <div class="detail-card-actions">
      <a href="{{ route('portal.track') }}" class="btn-secondary">
        <svg><use href="#i-chevron-left"/></svg>
        Cari Surat Lain
      </a>
      @if($permit->status === 'approved')
        <a href="{{ route('loading.letter', $permit->permit_number) }}" class="btn-primary" target="_blank">
          <svg><use href="#i-file"/></svg>
          Buka Surat Izin Resmi
        </a>
      @endif
    </div>
  </div>

</div>
@endsection
