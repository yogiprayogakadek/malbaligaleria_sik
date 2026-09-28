@extends('layouts.portal')

@section('title', 'Formulir ' . $typeInfo['title'])
@section('page-title', 'Formulir Permohonan')

@section('content')
<section class="view active" id="view-form" aria-labelledby="formTitle">
  <div class="page-wrap page-wrap--narrow">
    <a href="{{ route('portal.dashboard') }}" class="back-btn">
      <svg><use href="#i-arrow-left"/></svg>
      Kembali ke beranda
    </a>

    <div class="page-header page-header--form">
      <div>
        <p class="page-eyebrow" id="formEyebrow">FORMULIR PENGAJUAN</p>
        <h1 id="formTitle" class="page-title">{{ $typeInfo['title'] }}</h1>
        <p id="formSubtitle" class="page-subtitle">{{ $typeInfo['subtitle'] }}</p>
      </div>
    </div>

    <!-- Quick Type Switcher Tabs -->
    <div class="auth-tabs" style="margin-bottom: 24px;">
      <a href="{{ route('loading.create') }}"
         class="auth-tab {{ $type === 'loading' ? 'active' : '' }}">Loading</a>
      <a href="{{ route('work-permits.create') }}"
         class="auth-tab {{ $type === 'work' ? 'active' : '' }}">Kerja (SIK)</a>
    </div>

    <div class="alert-info">
      <svg><use href="#i-info"/></svg>
      <span>Format permohonan resmi tenant Mal Bali Galeria. Lengkapi formulir di bawah ini dengan data yang benar.</span>
    </div>

    <form id="requestForm" action="{{ route('permits.store') }}" method="POST" novalidate>
      @csrf
      <input type="hidden" name="permitType" id="permitType" value="{{ $type }}">

      <!-- STEP 01 -->
      <div class="form-section">
        <div class="form-section-header">
          <span class="step-badge">01</span>
          <div>
            <h2>Informasi Tenant</h2>
            <p>Identitas tenant dan penanggung jawab permohonan.</p>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="company">Nama tenant / perusahaan <span class="required">*</span></label>
            <input id="company" name="company" autocomplete="organization" placeholder="Contoh: PT Artisan Sejahtera" required>
          </div>
          <div class="form-group">
            <label for="unit">Nomor unit / lokasi <span class="required">*</span></label>
            <input id="unit" name="unit" placeholder="Contoh: Unit GF-08" required>
          </div>
          <div class="form-group">
            <label for="contact">Nama penanggung jawab <span class="required">*</span></label>
            <input id="contact" name="contact" autocomplete="name" placeholder="Nama lengkap" required>
          </div>
          <div class="form-group">
            <label for="phone">Nomor WhatsApp aktif <span class="required">*</span></label>
            <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="Contoh: 081234567890" minlength="9" required>
            <span class="form-hint">Digunakan untuk koordinasi dan pemantauan status izin.</span>
          </div>
        </div>
      </div>

      <!-- STEP 02 -->
      <div class="form-section">
        <div class="form-section-header">
          <span class="step-badge">02</span>
          <div>
            <h2>Waktu &amp; Lokasi</h2>
            <p>Jadwal pelaksanaan dan area kegiatan di gedung.</p>
          </div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="startDate">Tanggal mulai <span class="required">*</span></label>
            <input id="startDate" name="startDate" type="date" required>
          </div>
          <div class="form-group">
            <label for="endDate">Tanggal selesai <span class="required">*</span></label>
            <input id="endDate" name="endDate" type="date" required>
          </div>
          <div class="form-group">
            <label for="startTime">Jam mulai <span class="required">*</span></label>
            <input id="startTime" name="startTime" type="time" required>
          </div>
          <div class="form-group">
            <label for="endTime">Jam selesai <span class="required">*</span></label>
            <input id="endTime" name="endTime" type="time" required>
          </div>
          <div class="form-group form-group--full">
            <label for="location">Area kegiatan <span class="required">*</span></label>
            <input id="location" name="location" placeholder="Contoh: Loading dock timur / Koridor barat lt. 2" required>
          </div>
        </div>
      </div>

      <!-- STEP 03 -->
      <div class="form-section">
        <div class="form-section-header">
          <span class="step-badge">03</span>
          <div>
            <h2 id="detailsHeading">{{ $typeInfo['details'] }}</h2>
            <p>Rincian teknis untuk verifikasi pengelola gedung.</p>
          </div>
        </div>

        @if($type === 'loading')
          <div id="loadingFields" class="type-fields">
            <div class="form-grid">
              <div class="form-group">
                <label for="movement">Arah perpindahan <span class="required">*</span></label>
                <select id="movement" name="movement" required>
                  <option value="">Pilih arah perpindahan</option>
                  <option value="loading">Loading (barang keluar)</option>
                  <option value="unloading">Unloading (barang masuk)</option>
                </select>
              </div>
              <div class="form-group">
                <label for="vehicle">Nomor polisi kendaraan <span class="required">*</span></label>
                <input id="vehicle" name="vehicle" placeholder="Contoh: DK 9876 AB" required>
              </div>
              <div class="form-group">
                <label for="driver">Nama pengemudi <span class="required">*</span></label>
                <input id="driver" name="driver" placeholder="Nama pengemudi" required>
              </div>
              <div class="form-group">
                <label for="vendor">Vendor / ekspedisi</label>
                <input id="vendor" name="vendor" placeholder="Nama vendor (jika ada)">
              </div>
            </div>
            <div class="subsection-label">Daftar muatan barang <span class="required">*</span></div>
            <div id="itemsList" class="item-list">
              <div class="item-row">
                <input class="item-name" name="item_name[]" aria-label="Nama barang" placeholder="Nama barang" maxlength="120" required>
                <input class="item-qty" name="item_qty[]" aria-label="Jumlah barang" type="number" min="1" max="100000" placeholder="Jumlah" required>
                <button type="button" class="remove-item-btn" aria-label="Hapus barang" disabled>
                  <svg><use href="#i-trash"/></svg>
                </button>
              </div>
            </div>
            <button type="button" id="addItem" class="btn-secondary btn-sm">
              <svg><use href="#i-plus"/></svg>
              Tambah item barang
            </button>
          </div>
        @elseif($type === 'work')
          <div id="workFields" class="type-fields">
            <div class="form-grid">
              <div class="form-group">
                <label for="workCategory">Kategori pekerjaan <span class="required">*</span></label>
                <select id="workCategory" name="workCategory" required>
                  <option value="">Pilih kategori</option>
                  <option value="Renovasi">Renovasi unit</option>
                  <option value="Perbaikan">Perbaikan mekanikal</option>
                  <option value="Instalasi">Instalasi elektrikal</option>
                  <option value="Pemeliharaan">Pemeliharaan rutin</option>
                  <option value="Lainnya">Lainnya</option>
                </select>
              </div>
              <div class="form-group">
                <label for="workerCount">Jumlah pekerja <span class="required">*</span></label>
                <input id="workerCount" name="workerCount" type="number" min="1" max="500" placeholder="Contoh: 5" required>
              </div>
              <div class="form-group form-group--full">
                <label for="contractor">Kontraktor pelaksana <span class="required">*</span></label>
                <input id="contractor" name="contractor" placeholder="Nama vendor atau kontraktor" required>
              </div>
              <div class="form-group form-group--full">
                <label for="workDescription">Uraian lingkup kerja <span class="required">*</span></label>
                <textarea id="workDescription" name="workDescription" rows="3" placeholder="Uraikan metode pekerjaan dan dampak potensial" required></textarea>
              </div>
            </div>
          </div>
        @elseif($type === 'exhibition')
          <div id="exhibitionFields" class="type-fields">
            <div class="form-grid">
              <div class="form-group">
                <label for="exhibitionName">Nama pameran <span class="required">*</span></label>
                <input id="exhibitionName" name="exhibitionName" placeholder="Contoh: Summer Showcase" required>
              </div>
              <div class="form-group">
                <label for="exhibitionSize">Dimensi area booth</label>
                <input id="exhibitionSize" name="exhibitionSize" placeholder="Contoh: 4 x 4 meter">
              </div>
              <div class="form-group form-group--full">
                <label for="exhibitionDescription">Deskripsi konsep &amp; instalasi <span class="required">*</span></label>
                <textarea id="exhibitionDescription" name="exhibitionDescription" rows="3" placeholder="Kebutuhan daya, struktur partisi, penataan area" required></textarea>
              </div>
            </div>
          </div>
        @elseif($type === 'event')
          <div id="eventFields" class="type-fields">
            <div class="form-grid">
              <div class="form-group">
                <label for="eventName">Nama kegiatan <span class="required">*</span></label>
                <input id="eventName" name="eventName" placeholder="Contoh: Workshop Interior" required>
              </div>
              <div class="form-group">
                <label for="attendees">Estimasi peserta <span class="required">*</span></label>
                <input id="attendees" name="attendees" type="number" min="1" max="100000" placeholder="Contoh: 50" required>
              </div>
              <div class="form-group form-group--full">
                <label for="eventDescription">Rangkaian agenda <span class="required">*</span></label>
                <textarea id="eventDescription" name="eventDescription" rows="3" placeholder="Susunan acara dan kebutuhan teknis/keamanan" required></textarea>
              </div>
            </div>
          </div>
        @endif
      </div>

      <!-- STEP 04 -->
      <div class="form-section">
        <div class="form-section-header">
          <span class="step-badge">04</span>
          <div>
            <h2>Catatan Tambahan</h2>
            <p>Keterangan khusus untuk tim building management.</p>
          </div>
        </div>
        <div class="form-group">
          <label for="notes">Catatan (opsional)</label>
          <textarea id="notes" name="notes" rows="3" placeholder="Tuliskan catatan khusus atau permohonan dispensasi jika ada"></textarea>
        </div>
      </div>

      <div class="submit-area">
        <label class="checkbox-label">
          <input type="checkbox" id="consent" required>
          <span>Saya menyatakan data yang diisi benar dan menyetujui regulasi operasional gedung yang berlaku. <strong class="required">*</strong></span>
        </label>
        <button type="submit" class="btn-primary btn-primary--full">
          Kirim permohonan izin
          <svg><use href="#i-arrow-right"/></svg>
        </button>
      </div>
    </form>
  </div>
</section>
@endsection

@push('scripts')
<script>
  // Minimum date limit = today
  const today = new Date().toISOString().split('T')[0];
  const startDate = document.getElementById('startDate');
  const endDate = document.getElementById('endDate');
  if (startDate) startDate.min = today;
  if (endDate) endDate.min = today;

  // Add Item Row handler
  const addItemBtn = document.getElementById('addItem');
  const itemsList = document.getElementById('itemsList');

  function syncItemRemoveButtons() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(r => {
      const btn = r.querySelector('.remove-item-btn');
      if (btn) btn.disabled = rows.length === 1;
    });
  }

  if (addItemBtn && itemsList) {
    addItemBtn.addEventListener('click', () => {
      const row = document.createElement('div');
      row.className = 'item-row';
      row.innerHTML = `
        <input class="item-name" name="item_name[]" aria-label="Nama barang" placeholder="Nama barang" maxlength="120" required>
        <input class="item-qty" name="item_qty[]" aria-label="Jumlah barang" type="number" min="1" max="100000" placeholder="Jumlah" required>
        <button type="button" class="remove-item-btn" aria-label="Hapus barang">
          <svg><use href="#i-trash"/></svg>
        </button>
      `;
      itemsList.appendChild(row);
      syncItemRemoveButtons();
    });

    itemsList.addEventListener('click', (e) => {
      const btn = e.target.closest('.remove-item-btn');
      if (btn) {
        const rows = document.querySelectorAll('.item-row');
        if (rows.length > 1) {
          btn.closest('.item-row')?.remove();
          syncItemRemoveButtons();
        }
      }
    });
  }

  // Real-time invalid clearing
  document.getElementById('requestForm')?.addEventListener('input', (e) => {
    e.target.classList.remove('is-invalid');
  });
</script>
@endpush
