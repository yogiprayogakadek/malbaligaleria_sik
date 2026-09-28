@extends('layouts.portal')

@section('title', 'Detail Permohonan ' . $permit->permit_number . ' : Mal Bali Galeria')
@section('page-title', 'Detail Permohonan')

@section('content')
<div class="page-wrap page-wrap--narrow">

  <div class="page-header">
    <div>
      <div class="page-breadcrumb">
        <a href="{{ route('portal.dashboard') }}">Beranda</a>
        <svg width="14" height="14"><use href="#i-chevron-right"/></svg>
        <a href="{{ route('permits.index') }}">Permohonan Saya</a>
        <svg width="14" height="14"><use href="#i-chevron-right"/></svg>
        <span>Detail</span>
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
        <p>Disetujui oleh {{ $permit->reviewer?->name ?? 'Tim TR' }} pada {{ $permit->reviewed_at?->format('d M Y, H:i') }} WITA. Surat izin telah diterbitkan dan siap digunakan.</p>
      @elseif($permit->status === 'rejected')
        <p>Permohonan belum dapat disetujui. Catatan dari tim verifikasi: "{{ $permit->review_notes }}"</p>
      @else
        <p>Permohonan telah diterima dan sedang dalam antrean verifikasi operasional divisi Tenant Relationship (TR).</p>
      @endif
    </div>
  </div>

  <div class="detail-card">
    <div class="detail-card-header">
      <h2 class="detail-card-title">Informasi Permohonan</h2>
      <span class="badge-status badge-status--{{ $permit->status }}">{{ $permit->status_label }}</span>
    </div>

    <dl class="detail-dl">
      <dt>Nomor Surat</dt>
      <dd><strong>{{ $permit->permit_number }}</strong></dd>

      <dt>Nama Tenant</dt>
      <dd>{{ $permit->tenant_name }}</dd>

      <dt>Penanggung Jawab (PIC)</dt>
      <dd>{{ $permit->applicant_name }} ({{ $permit->applicant_phone }})</dd>

      @if($permit->applicant_email)
        <dt>Email PIC</dt>
        <dd>{{ $permit->applicant_email }}</dd>
      @endif

      <dt>Arah Loading</dt>
      <dd>{{ $permit->direction_label }}</dd>

      <dt>Periode Izin</dt>
      <dd>{{ $permit->start_date->format('d M Y') }} sampai {{ $permit->end_date->format('d M Y') }}</dd>

      <dt>{{ $permit->movement_time_field_label }}</dt>
      <dd>{{ $permit->movement_time_label }}</dd>

      <dt>Jumlah Barang</dt>
      <dd>{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</dd>

      <dt>Keterangan Barang</dt>
      <dd>{{ $permit->item_description ?: '-' }}</dd>

      @if($permit->vehicle_plate)
        <dt>Nomor Polisi Kendaraan</dt>
        <dd>{{ $permit->vehicle_plate }}</dd>
      @endif

      <dt>Dokumen Identitas</dt>
      <dd>Foto {{ strtoupper($permit->id_doc_type) }} telah terverifikasi sistem</dd>

      <dt>Waktu Pengajuan</dt>
      <dd>{{ $permit->created_at->format('d M Y, H:i') }} WITA</dd>

      @if($permit->review_notes && $permit->status === 'approved')
        <dt>Catatan Khusus</dt>
        <dd>{{ $permit->review_notes }}</dd>
      @endif
    </dl>

    <div class="detail-card-actions">
      <a href="{{ route('permits.index') }}" class="btn-secondary">
        <svg><use href="#i-chevron-left"/></svg>
        Kembali ke Daftar
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
