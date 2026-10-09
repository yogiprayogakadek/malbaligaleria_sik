@extends('layouts.portal')

@section('title', 'Cek Status Izin : Mal Bali Galeria')
@section('page-title', 'Cek Status')

@section('content')
<section class="view active" id="view-track" aria-labelledby="trackTitle">
  <div class="page-wrap page-wrap--narrow">
    <div class="page-header">
      <div>
        <p class="page-eyebrow">PELACAKAN STATUS TERVERIFIKASI</p>
        <h1 id="trackTitle" class="page-title">Cek Status Izin</h1>
        <p class="page-subtitle">Sistem pelacakan aman dilindungi verifikasi ganda. Masukkan nomor surat dan nomor WhatsApp penanggung jawab (PIC) yang terdaftar.</p>
      </div>
    </div>

    {{-- Form Pencarian Surat dengan Realtime Validation & Anti-Enumeration --}}
    <div class="track-card" style="margin-bottom: 24px;">
      <form id="trackForm" class="track-form" action="{{ route('portal.track') }}" method="GET" novalidate>
        <div style="display: flex; flex-direction: column; gap: 18px;">
          
          {{-- Field 1: Nomor Surat Izin --}}
          <div class="form-group" style="margin-bottom: 0;">
            <label for="trackPermitNumber">
              Nomor Surat Izin <span style="color: #ef4444;">*</span>
            </label>
            <input id="trackPermitNumber"
                   name="permit_number"
                   type="text"
                   value="{{ $reference }}"
                   placeholder="Contoh: MBG/SIK/IX/0001"
                   autocomplete="off"
                   maxlength="40"
                   required>
            <div id="permitHint" class="realtime-hint">
              <span class="hint-text" style="color: #64748b;">Format resmi: <code>MBG/SIK/[BULAN]/[NOMOR]</code> (hanya huruf, angka, dan garis miring).</span>
            </div>
          </div>

          {{-- Field 2: Nomor WhatsApp / HP PIC --}}
          <div class="form-group" style="margin-bottom: 0;">
            <label for="trackPhone">
              Nomor WhatsApp / HP PIC Penanggung Jawab <span style="color: #ef4444;">*</span>
            </label>
            <input id="trackPhone"
                   name="phone"
                   type="tel"
                   value="{{ $phoneInput }}"
                   placeholder="Contoh: 081234567890 atau +6281234567890"
                   autocomplete="off"
                   maxlength="20"
                   required>
            <div id="phoneHint" class="realtime-hint">
              <span class="hint-text" style="color: #64748b;">Wajib nomor lengkap PIC yang didaftarkan (minimal 9 digit angka).</span>
            </div>
          </div>

          {{-- Tombol Submit Terproteksi --}}
          <div style="padding-top: 6px;">
            <button class="btn-primary btn-primary--full" id="btnSubmitTrack" type="submit" disabled>
              <svg width="18" height="18"><use href="#i-shield-check"/></svg>
              <span id="btnSubmitText">Verifikasi &amp; Periksa Status</span>
            </button>
          </div>

        </div>
      </form>

      {{-- Subtle privacy note --}}
      <p style="display: flex; align-items: center; gap: 6px; margin-top: 16px; font-size: 12px; color: #94a3b8;">
        <svg width="14" height="14" style="flex-shrink:0;"><use href="#i-lock"/></svg>
        Data permohonan hanya dapat diakses oleh penanggung jawab yang terdaftar.
      </p>
    </div>

    {{-- Alert Error: Pesan Keamanan Terpadu --}}
    @if($securityError)
      <div class="status-banner status-banner--rejected" style="margin-bottom: 24px;">
        <div class="status-banner-icon">
          <svg><use href="#i-info"/></svg>
        </div>
        <div class="status-banner-content">
          <strong>Verifikasi Tidak Berhasil</strong>
          <p>{{ $securityError }}</p>
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

        // Masking nomor telepon untuk proteksi tampilan publik (contoh: 0812****7890)
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
            <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('loading.letter.preview', now()->addMinutes(15), ['permitNumber' => $permit->permit_number]) }}" class="btn-primary">
              <svg><use href="#i-file"/></svg>
              <span>Lihat Surat Izin Resmi</span>
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
        <p>Jika Anda mengajukan permohonan saat login, Anda dapat melihat seluruh riwayat surat di menu <a href="{{ route('permits.index') }}">Permohonan Saya</a>.</p>
      </div>
    </div>

  </div>
</section>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  const permitInput = document.getElementById('trackPermitNumber');
  const phoneInput  = document.getElementById('trackPhone');
  const permitHint  = document.getElementById('permitHint');
  const phoneHint   = document.getElementById('phoneHint');
  const submitBtn   = document.getElementById('btnSubmitTrack');
  const trackForm   = document.getElementById('trackForm');

  if (!permitInput || !phoneInput || !submitBtn) return;

  // Pola regex keamanan nomor surat: MBG/SIK/[HURUF_ANGKA]/[ANGKA]
  const permitPattern = /^MBG\/SIK\/[A-Za-z0-9_-]+\/[0-9]+$/;

  // Sanitasi & Realtime Validation untuk Nomor Surat
  function validatePermit(showFeedback = true) {
    let val = permitInput.value.trim().toUpperCase();
    
    // Hapus karakter terlarang (XSS / SQLi protection)
    val = val.replace(/[^A-Z0-9\/\-_]/g, '');
    if (permitInput.value !== val) {
      permitInput.value = val;
    }

    if (!val) {
      if (showFeedback) {
        permitInput.classList.remove('is-valid', 'is-invalid');
        permitHint.className = 'realtime-hint';
        permitHint.innerHTML = '<span style="color: #64748b;">Format resmi: <code>MBG/SIK/[BULAN]/[NOMOR]</code> (contoh: MBG/SIK/IX/0001).</span>';
      }
      return false;
    }

    const isValid = permitPattern.test(val);
    if (showFeedback) {
      if (isValid) {
        permitInput.classList.remove('is-invalid');
        permitInput.classList.add('is-valid');
        permitHint.className = 'realtime-hint realtime-hint--valid';
        permitHint.innerHTML = '<span>✓ Format nomor surat valid</span>';
      } else {
        permitInput.classList.remove('is-valid');
        permitInput.classList.add('is-invalid');
        permitHint.className = 'realtime-hint realtime-hint--error';
        permitHint.innerHTML = '<span>✗ Format harus sesuai contoh: <code>MBG/SIK/IX/0001</code></span>';
      }
    }
    return isValid;
  }

  // Sanitasi & Realtime Validation untuk Nomor WhatsApp / HP
  function validatePhone(showFeedback = true) {
    let val = phoneInput.value.trim();

    // Hapus karakter selain angka, +, -, spasi, ()
    val = val.replace(/[^0-9+\s\-()]/g, '');
    if (phoneInput.value !== val) {
      phoneInput.value = val;
    }

    const cleanDigits = val.replace(/\D/g, '');

    if (!val) {
      if (showFeedback) {
        phoneInput.classList.remove('is-valid', 'is-invalid');
        phoneHint.className = 'realtime-hint';
        phoneHint.innerHTML = '<span style="color: #64748b;">Wajib nomor lengkap PIC yang didaftarkan (minimal 9 digit angka).</span>';
      }
      return false;
    }

    // Validasi panjang digit angka minimal 9 dan awalan wajar
    const hasValidLength = cleanDigits.length >= 9 && cleanDigits.length <= 16;
    const hasValidPrefix = cleanDigits.startsWith('08') || cleanDigits.startsWith('62') || cleanDigits.startsWith('0') || cleanDigits.length >= 10;

    const isValid = hasValidLength && hasValidPrefix;

    if (showFeedback) {
      if (isValid) {
        phoneInput.classList.remove('is-invalid');
        phoneInput.classList.add('is-valid');
        phoneHint.className = 'realtime-hint realtime-hint--valid';
        phoneHint.innerHTML = `<span>✓ Nomor kontak valid (${cleanDigits.length} digit)</span>`;
      } else {
        phoneInput.classList.remove('is-valid');
        phoneInput.classList.add('is-invalid');
        phoneHint.className = 'realtime-hint realtime-hint--error';
        if (cleanDigits.length < 9) {
          phoneHint.innerHTML = `<span>✗ Nomor HP masih kurang lengkap (${cleanDigits.length}/9 digit minimum)</span>`;
        } else {
          phoneHint.innerHTML = '<span>✗ Format nomor telepon tidak sesuai standar</span>';
        }
      }
    }
    return isValid;
  }

  // Cek kesiapan form & toggle state tombol submit
  function checkFormValidity() {
    const isPermitValid = validatePermit(false);
    const isPhoneValid  = validatePhone(false);
    submitBtn.disabled  = !(isPermitValid && isPhoneValid);
  }

  // Event Listeners Realtime
  permitInput.addEventListener('input', () => {
    validatePermit(true);
    checkFormValidity();
  });

  permitInput.addEventListener('blur', () => {
    validatePermit(true);
    checkFormValidity();
  });

  phoneInput.addEventListener('input', () => {
    validatePhone(true);
    checkFormValidity();
  });

  phoneInput.addEventListener('blur', () => {
    validatePhone(true);
    checkFormValidity();
  });

  // Cegah submit jika data belum valid
  trackForm.addEventListener('submit', function (e) {
    const isPermitValid = validatePermit(true);
    const isPhoneValid  = validatePhone(true);

    if (!isPermitValid || !isPhoneValid) {
      e.preventDefault();
      checkFormValidity();
      return false;
    }

    submitBtn.disabled = true;
    document.getElementById('btnSubmitText').textContent = 'Memverifikasi Data...';
  });

  // Inisialisasi awal saat halaman dibuka
  validatePermit(permitInput.value.length > 0);
  validatePhone(phoneInput.value.length > 0);
  checkFormValidity();

})();
</script>
@endpush
