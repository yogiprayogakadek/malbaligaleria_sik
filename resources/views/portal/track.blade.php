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
        <p class="page-subtitle">Masukkan nomor surat dan nomor kontak PIC penanggung jawab untuk memverifikasi serta memeriksa status izin.</p>
      </div>
    </div>

    {{-- Form Pencarian Surat dengan Verifikasi Keamanan --}}
    <div class="track-card" style="margin-bottom: 24px;">
      <form id="trackForm" class="track-form" action="{{ route('portal.track') }}" method="GET">
        <div style="display: flex; flex-direction: column; gap: 16px;">
          
          <div class="form-group @if($searched && !$permit && !$phoneMismatch) form-group--error @endif" style="margin-bottom: 0;">
            <label for="trackPermitNumber">Nomor Surat Izin <span style="color: #ef4444;">*</span></label>
            <input id="trackPermitNumber"
                   name="permit_number"
                   type="text"
                   value="{{ $reference }}"
                   placeholder="Contoh: MBG/SIK/IX/0001"
                   autocomplete="off"
                   required>
            <span class="form-hint">Format nomor surat sesuai yang tertera pada bukti pendaftaran.</span>
          </div>

          <div class="form-group @if($phoneMismatch) form-group--error @endif" style="margin-bottom: 0;">
            <label for="trackPhone">
              Nomor WhatsApp / HP PIC 
              @auth
                <small style="color: #64748b; font-weight: normal;">(Opsional jika login sebagai pemilik surat)</small>
              @else
                <span style="color: #ef4444;">*</span>
              @endauth
            </label>
            <input id="trackPhone"
                   name="phone"
                   type="text"
                   value="{{ $phoneInput }}"
                   placeholder="Nomor HP lengkap atau 4 digit terakhir (contoh: 7890)"
                   autocomplete="off"
                   @guest required @endguest>
            <span class="form-hint">Untuk privasi &amp; keamanan, verifikasi memerlukan nomor kontak PIC atau minimal 4 digit terakhir nomor telepon pemohon.</span>
          </div>

          <div style="padding-top: 4px;">
            <button class="btn-primary btn-primary--full" type="submit">
              <span>Periksa Status Surat</span>
              <svg width="18" height="18"><use href="#i-search"/></svg>
            </button>
          </div>

        </div>
      </form>

      <div style="display: flex; align-items: center; gap: 8px; margin-top: 16px; padding: 10px 14px; background: #f8fafc; border-radius: 8px; font-size: 12.5px; color: #64748b;">
        <svg width="16" height="16" style="flex-shrink: 0; color: #0284c7;"><use href="#i-shield-check"/></svg>
        <span><strong>Perlindungan Privasi:</strong> Data surat izin dilindungi verifikasi ganda agar tidak dapat diakses sembarang pihak.</span>
      </div>
    </div>

    {{-- Alert 1: Surat Tidak Ditemukan --}}
    @if($searched && !$permit && !$phoneMismatch)
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

    {{-- Alert 2: Nomor Surat Ada tapi Nomor HP Tidak Cocok --}}
    @if($phoneMismatch)
      <div class="status-banner status-banner--rejected" style="margin-bottom: 24px;">
        <div class="status-banner-icon">
          <svg><use href="#i-lock"/></svg>
        </div>
        <div class="status-banner-content">
          <strong>Verifikasi Keamanan Gagal</strong>
          <p>Nomor WhatsApp/HP yang Anda masukkan tidak sesuai dengan kontak penanggung jawab (PIC) pada nomor surat ini. Demi keamanan tenant, pastikan Anda memasukkan nomor HP atau 4 digit terakhir nomor WhatsApp yang didaftarkan.</p>
        </div>
      </div>
    @endif

    {{-- Hasil Pencarian: Permohonan Terverifikasi --}}
    @if($permit)
      @php
        $bannerClass = match($permit->status) {
          'approved' => 'status-banner--approved',
          'rejected' => 'status-banner--rejected',
          default    => 'status-banner--pending',
        };

        // Masking nomor telepon untuk tampilan publik (contoh: 0812****7890)
        $cleanDigits = preg_replace('/\D+/', '', (string) $permit->applicant_phone);
        if (strlen($cleanDigits) >= 8) {
          $maskedPhone = substr($cleanDigits, 0, 4) . '****' . substr($cleanDigits, -4);
        } else {
          $maskedPhone = '****' . substr($cleanDigits, -4);
        }
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
          <dt>Penanggung Jawab (PIC)</dt><dd>{{ $permit->applicant_name }} ({{ $maskedPhone }})</dd>
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
        <strong>Lupa Nomor Surat atau Kontak PIC?</strong>
        <p>Jika Anda mengajukan permohonan saat login, Anda dapat melihat seluruh riwayat surat di menu <a href="{{ route('permits.index') }}">Permohonan Saya</a> tanpa perlu memasukkan nomor HP lagi.</p>
      </div>
    </div>

  </div>
</section>
@endsection
