@extends('layouts.portal')

@section('title', 'Cek Status Izin : Mal Bali Galeria')
@section('page-title', 'Cek Status')

@section('content')
<section class="view active" id="view-track" aria-labelledby="trackTitle">
  <div class="page-wrap page-wrap--narrow">
    <div class="page-header">
      <div>
        <p class="page-eyebrow">PELACAKAN STATUS</p>
        <h1 id="trackTitle" class="page-title">Cek Status Izin</h1>
        <p class="page-subtitle">Masukkan nomor surat permohonan izin untuk memeriksa status verifikasi secara langsung.</p>
      </div>
    </div>

    {{-- Form Pencarian Surat --}}
    <div class="track-card" style="margin-bottom: 24px;">
      <form id="trackForm" class="track-form" action="{{ route('portal.track') }}" method="GET">
        <div class="form-group @if($searched && !$permit) form-group--error @endif">
          <label for="trackPermitNumber">Nomor Surat Izin</label>
          <div style="display: flex; gap: 8px;">
            <input id="trackPermitNumber"
                   name="permit_number"
                   type="text"
                   value="{{ $reference }}"
                   placeholder="Contoh: MBG/SIK/IX/0001"
                   autocomplete="off"
                   style="flex: 1;"
                   required>
            <button class="btn-primary" type="submit" style="white-space: nowrap; padding: 0 20px;">
              <span>Periksa</span>
              <svg width="16" height="16"><use href="#i-search"/></svg>
            </button>
          </div>
          <span class="form-hint">Nomor surat diperoleh setelah formulir permohonan berhasil dikirimkan.</span>
        </div>
      </form>
    </div>

    {{-- Hasil Pencarian: Permohonan Tidak Ditemukan --}}
    @if($searched && !$permit)
      <div class="status-banner status-banner--rejected" style="margin-bottom: 24px;">
        <div class="status-banner-icon">
          <svg><use href="#i-info"/></svg>
        </div>
        <div class="status-banner-content">
          <strong>Permohonan Tidak Ditemukan</strong>
          <p>Nomor surat <strong>"{{ $reference }}"</strong> tidak terdaftar dalam sistem. Pastikan format penulisan nomor surat sudah benar (contoh: <code>MBG/SIK/IX/0001</code>).</p>
        </div>
      </div>
    @endif

    {{-- Hasil Pencarian: Permohonan Ditemukan --}}
    @if($permit)
      @php
        $bannerClass = match($permit->status) {
          'approved' => 'status-banner--approved',
          'rejected' => 'status-banner--rejected',
          default    => 'status-banner--pending',
        };
      @endphp

      {{-- Status Banner --}}
      <div class="status-banner {{ $bannerClass }}">
        <div class="status-banner-icon">
          <svg><use href="#i-{{ $permit->status === 'approved' ? 'check' : ($permit->status === 'rejected' ? 'info' : 'clock') }}"/></svg>
        </div>
        <div class="status-banner-content">
          <strong>Status: {{ $permit->status_label }}</strong>
          @if($permit->status === 'approved')
            <p>Permohonan disetujui pada {{ $permit->reviewed_at?->format('d M Y, H:i') }} WITA. Surat izin resmi telah diterbitkan dan dapat diunduh.</p>
          @elseif($permit->status === 'rejected')
            <p>Permohonan belum dapat disetujui oleh tim verifikasi TR. Catatan: "{{ $permit->review_notes ?: 'Tidak ada catatan tambahan.' }}"</p>
          @else
            <p>Permohonan telah diterima dan sedang dalam antrean verifikasi operasional divisi Tenant Relationship (TR).</p>
          @endif
        </div>
      </div>

      {{-- Detail Permohonan --}}
      <div class="detail-card" style="margin-bottom: 24px;">
        <div class="detail-card-header">
          <h2 class="detail-card-title">Detail Permohonan Izin</h2>
          <span class="badge-status badge-status--{{ $permit->status }}">{{ $permit->status_label }}</span>
        </div>

        <dl class="detail-dl">
          <dt>Nomor Surat</dt><dd><strong>{{ $permit->permit_number }}</strong></dd>
          <dt>Nama Tenant</dt><dd>{{ $permit->tenant_name }}</dd>
          <dt>Penanggung Jawab (PIC)</dt><dd>{{ $permit->applicant_name }} ({{ $permit->applicant_phone }})</dd>
          @if($permit->applicant_email)
            <dt>Email</dt><dd>{{ $permit->applicant_email }}</dd>
          @endif
          <dt>Arah Loading</dt><dd>{{ $permit->direction_label }}</dd>
          <dt>Tanggal Mulai</dt><dd>{{ $permit->start_date->format('d M Y') }}</dd>
          <dt>Tanggal Selesai</dt><dd>{{ $permit->end_date->format('d M Y') }}</dd>
          <dt>Jumlah Barang</dt><dd>{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</dd>
          <dt>Keterangan</dt><dd>{{ $permit->item_description ?: '-' }}</dd>
          @if($permit->vehicle_plate)
            <dt>No. Kendaraan</dt><dd>{{ $permit->vehicle_plate }}</dd>
          @endif
          <dt>Waktu Pengajuan</dt><dd>{{ $permit->created_at->format('d M Y, H:i') }} WITA</dd>
        </dl>

        <div class="detail-card-actions">
          @if($permit->status === 'approved')
            <a href="{{ route('loading.letter', $permit->permit_number) }}" class="btn-primary" target="_blank">
              <svg><use href="#i-file"/></svg>
              <span>Unduh / Cetak Surat Izin Resmi</span>
            </a>
          @endif
          <a href="{{ route('portal.track') }}" class="btn-secondary">
            <svg><use href="#i-chevron-left"/></svg>
            <span>Cari Surat Lain</span>
          </a>
        </div>
      </div>
    @endif

    {{-- Kotak Bantuan --}}
    <div class="track-help-box">
      <svg width="20" height="20"><use href="#i-help"/></svg>
      <div>
        <strong>Lupa Nomor Surat?</strong>
        <p>Jika Anda mengajukan permohonan saat login, Anda dapat melihat seluruh riwayat surat di menu <a href="{{ route('permits.index') }}">Permohonan Saya</a>.</p>
      </div>
    </div>

  </div>
</section>
@endsection
