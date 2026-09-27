@extends('layouts.app')

@section('title', 'Mal Bali Galeria : Portal Izin Tenant')

@section('content')
<div class="app-shell" id="appShell">

  <!-- Desktop Sidebar: icon-only -->
  <aside class="desktop-sidebar" aria-label="Navigasi utama">
    <a href="#beranda" class="sidebar-logo" data-view-link="home" aria-label="Mal Bali Galeria beranda">
      <img src="{{ asset('logo.png') }}" alt="MBG" class="sidebar-logo-img">
    </a>

    <nav class="sidebar-nav" aria-label="Menu utama">
      <button type="button" class="nav-item active" data-view-link="home" aria-label="Beranda">
        <svg><use href="#i-grid"/></svg>
        <span class="nav-tooltip">Beranda</span>
      </button>
      <button type="button" class="nav-item" data-view-link="history" aria-label="Permohonan saya">
        <svg><use href="#i-file"/></svg>
        <span class="nav-tooltip">Permohonan</span>
      </button>
      <button type="button" class="nav-item" data-view-link="track" aria-label="Cek status">
        <svg><use href="#i-search"/></svg>
        <span class="nav-tooltip">Cek Status</span>
      </button>
      <button type="button" class="nav-item" data-view-link="help" aria-label="Bantuan">
        <svg><use href="#i-help"/></svg>
        <span class="nav-tooltip">Bantuan</span>
      </button>
    </nav>

    <div class="sidebar-bottom">
      <form action="{{ route('logout') }}" method="POST" class="logout-form">
        @csrf
        <button type="submit" class="nav-item nav-item-logout" id="logoutBtn" aria-label="Keluar dari portal" title="Keluar"><svg><use href="#i-logout"/></svg><span class="nav-tooltip">Keluar</span></button>
      </form>
    </div>
  </aside>

  <!-- Main Content -->
  <div class="content-shell">

    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <a href="#beranda" class="brand-mobile" data-view-link="home" aria-label="MBG Portal">
          <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="topbar-logo-mobile">
        </a>
      </div>
      <div class="topbar-center">
        <span class="topbar-title" id="topbarTitle">Beranda</span>
      </div>
      <div class="topbar-right">
        <span class="topbar-date" id="todayLabel"></span>
        <button type="button" class="topbar-btn" data-view-link="track" aria-label="Cek status">
          <svg><use href="#i-search"/></svg>
        </button>
        <form action="{{ route('logout') }}" method="POST" class="logout-form">
          @csrf
          <button type="submit" class="topbar-btn" id="mobileLogoutBtn" aria-label="Keluar dari portal" title="Keluar"><svg><use href="#i-logout"/></svg></button>
        </form>
      </div>
    </header>

    <main id="mainContent" tabindex="-1">

      <!-- HOME VIEW -->
      <section class="view active" id="view-home" aria-labelledby="home-title">
        <div class="page-wrap">
          <div class="page-header">
            <div>
              <p class="page-eyebrow">Portal Perizinan Tenant</p>
              <h1 id="home-title" class="page-title">Selamat datang di MBG</h1>
              <p class="page-subtitle">Ajukan surat izin operasional gedung Mal Bali Galeria secara mudah dan terstruktur.</p>
            </div>
            <button type="button" class="btn-primary" data-start="loading">
              <svg><use href="#i-plus"/></svg>
              Permohonan baru
            </button>
          </div>

          <div class="section-label">LAYANAN PERIZINAN</div>
          <div class="service-grid">

            <button type="button" class="service-card" data-start="loading">
              <div class="service-card-icon service-card-icon--blue">
                <svg><use href="#i-box"/></svg>
              </div>
              <div class="service-card-body">
                <span class="service-tag">LOGISTIK</span>
                <h3>Loading &amp; Unloading</h3>
                <p>Pengajuan izin keluar atau masuk barang, material, dan logistik di area gedung.</p>
              </div>
              <div class="service-card-footer">
                <span>Ajukan izin</span>
                <div class="service-arrow">
                  <svg><use href="#i-arrow-right"/></svg>
                </div>
              </div>
            </button>

            <button type="button" class="service-card" data-start="work">
              <div class="service-card-icon service-card-icon--violet">
                <svg><use href="#i-wrench"/></svg>
              </div>
              <div class="service-card-body">
                <span class="service-tag">OPERASIONAL</span>
                <h3>Surat Izin Kerja</h3>
                <p>Pengajuan SIK untuk renovasi, instalasi teknis, dan pekerjaan vendor di gedung.</p>
              </div>
              <div class="service-card-footer">
                <span>Ajukan izin</span>
                <div class="service-arrow">
                  <svg><use href="#i-arrow-right"/></svg>
                </div>
              </div>
            </button>

            <button type="button" class="service-card" data-start="exhibition">
              <div class="service-card-icon service-card-icon--rose">
                <svg><use href="#i-gallery"/></svg>
              </div>
              <div class="service-card-body">
                <span class="service-tag">PROMOSI</span>
                <h3>Surat Izin Pameran</h3>
                <p>Pengajuan izin aktivasi brand, display produk, dan penataan area pameran.</p>
              </div>
              <div class="service-card-footer">
                <span>Ajukan izin</span>
                <div class="service-arrow">
                  <svg><use href="#i-arrow-right"/></svg>
                </div>
              </div>
            </button>

            <button type="button" class="service-card" data-start="event">
              <div class="service-card-icon service-card-icon--amber">
                <svg><use href="#i-calendar"/></svg>
              </div>
              <div class="service-card-body">
                <span class="service-tag">ACARA</span>
                <h3>Surat Izin Event</h3>
                <p>Pengajuan izin penyelenggaraan acara, gathering, dan kegiatan khusus tenant.</p>
              </div>
              <div class="service-card-footer">
                <span>Ajukan izin</span>
                <div class="service-arrow">
                  <svg><use href="#i-arrow-right"/></svg>
                </div>
              </div>
            </button>

          </div>

          <div class="info-strip">
            <div class="info-strip-icon">
              <svg><use href="#i-shield-check"/></svg>
            </div>
            <div class="info-strip-body">
              <strong>Prosedur tanpa cetak dokumen</strong>
              <p>Isi formulir, simpan nomor referensi, dan pantau status izin kapan saja.</p>
            </div>
            <button type="button" class="info-strip-action" data-view-link="help" aria-label="Baca panduan">
              <svg><use href="#i-chevron-right"/></svg>
            </button>
          </div>

        </div>
      </section>

      <!-- FORM VIEW -->
      <section class="view" id="view-form" aria-labelledby="formTitle">
        <div class="page-wrap page-wrap--narrow">
          <button type="button" class="back-btn" data-view-link="home">
            <svg><use href="#i-arrow-left"/></svg>
            Kembali ke beranda
          </button>
          <div class="page-header page-header--form">
            <div>
              <p class="page-eyebrow" id="formEyebrow">FORMULIR PENGAJUAN</p>
              <h1 id="formTitle" class="page-title">Surat Izin</h1>
              <p id="formSubtitle" class="page-subtitle"></p>
            </div>
          </div>

          <div class="alert-info">
            <svg><use href="#i-info"/></svg>
            <span>Format permohonan digital resmi tenant Mal Bali Galeria. Lengkapi formulir di bawah ini.</span>
          </div>

          <form id="requestForm" novalidate>
            <input type="hidden" name="permitType" id="permitType">

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
                  <span class="form-hint">Digunakan untuk koordinasi dan pemantauan status.</span>
                </div>
              </div>
            </div>

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

            <div class="form-section">
              <div class="form-section-header">
                <span class="step-badge">03</span>
                <div>
                  <h2 id="detailsHeading">Detail Kegiatan</h2>
                  <p>Rincian teknis untuk verifikasi pengelola gedung.</p>
                </div>
              </div>

              <div id="loadingFields" class="type-fields" hidden>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="movement">Arah perpindahan <span class="required">*</span></label>
                    <select id="movement" name="movement">
                      <option value="">Pilih arah perpindahan</option>
                      <option value="loading">Loading (barang keluar)</option>
                      <option value="unloading">Unloading (barang masuk)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="vehicle">Nomor polisi kendaraan <span class="required">*</span></label>
                    <input id="vehicle" name="vehicle" placeholder="Contoh: B 9876 KLM">
                  </div>
                  <div class="form-group">
                    <label for="driver">Nama pengemudi <span class="required">*</span></label>
                    <input id="driver" name="driver" placeholder="Nama pengemudi">
                  </div>
                  <div class="form-group">
                    <label for="vendor">Vendor / ekspedisi</label>
                    <input id="vendor" name="vendor" placeholder="Nama vendor (jika ada)">
                  </div>
                </div>
                <div class="subsection-label">Daftar muatan barang <span class="required">*</span></div>
                <div id="itemsList" class="item-list"></div>
                <button type="button" id="addItem" class="btn-secondary btn-sm">
                  <svg><use href="#i-plus"/></svg>
                  Tambah item barang
                </button>
              </div>

              <div id="workFields" class="type-fields" hidden>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="workCategory">Kategori pekerjaan <span class="required">*</span></label>
                    <select id="workCategory" name="workCategory">
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
                    <input id="workerCount" name="workerCount" type="number" min="1" max="500" placeholder="Contoh: 5">
                  </div>
                  <div class="form-group form-group--full">
                    <label for="contractor">Kontraktor pelaksana <span class="required">*</span></label>
                    <input id="contractor" name="contractor" placeholder="Nama vendor atau kontraktor">
                  </div>
                  <div class="form-group form-group--full">
                    <label for="workDescription">Uraian lingkup kerja <span class="required">*</span></label>
                    <textarea id="workDescription" name="workDescription" rows="3" placeholder="Uraikan metode pekerjaan dan dampak potensial"></textarea>
                  </div>
                </div>
              </div>

              <div id="exhibitionFields" class="type-fields" hidden>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="exhibitionName">Nama pameran <span class="required">*</span></label>
                    <input id="exhibitionName" name="exhibitionName" placeholder="Contoh: Summer Showcase">
                  </div>
                  <div class="form-group">
                    <label for="exhibitionSize">Dimensi area booth</label>
                    <input id="exhibitionSize" name="exhibitionSize" placeholder="Contoh: 4 x 4 meter">
                  </div>
                  <div class="form-group form-group--full">
                    <label for="exhibitionDescription">Deskripsi konsep &amp; instalasi <span class="required">*</span></label>
                    <textarea id="exhibitionDescription" name="exhibitionDescription" rows="3" placeholder="Kebutuhan daya, struktur partisi, penataan area"></textarea>
                  </div>
                </div>
              </div>

              <div id="eventFields" class="type-fields" hidden>
                <div class="form-grid">
                  <div class="form-group">
                    <label for="eventName">Nama kegiatan <span class="required">*</span></label>
                    <input id="eventName" name="eventName" placeholder="Contoh: Workshop Interior">
                  </div>
                  <div class="form-group">
                    <label for="attendees">Estimasi peserta <span class="required">*</span></label>
                    <input id="attendees" name="attendees" type="number" min="1" max="100000" placeholder="Contoh: 50">
                  </div>
                  <div class="form-group form-group--full">
                    <label for="eventDescription">Rangkaian agenda <span class="required">*</span></label>
                    <textarea id="eventDescription" name="eventDescription" rows="3" placeholder="Susunan acara dan kebutuhan teknis/keamanan"></textarea>
                  </div>
                </div>
              </div>
            </div>

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

      <!-- SUCCESS VIEW -->
      <section class="view" id="view-success" aria-labelledby="successTitle">
        <div class="page-wrap page-wrap--narrow">
          <div class="success-card">
            <div class="success-icon">
              <svg><use href="#i-check"/></svg>
            </div>
            <p class="page-eyebrow">PERMOHONAN TERSIMPAN</p>
            <h1 id="successTitle">Pengajuan berhasil dicatat</h1>
            <p class="page-subtitle">Simpan nomor referensi berikut untuk memantau status persetujuan pengelola gedung.</p>
            <div class="reference-box">
              <span class="reference-label">NOMOR REFERENSI</span>
              <strong id="successReference"></strong>
              <button id="copyReference" type="button" class="reference-copy-btn">Salin nomor</button>
            </div>
            <div class="success-meta" id="successMeta"></div>
            <div class="alert-info">
              <svg><use href="#i-info"/></svg>
              <span>Status: Menunggu peninjauan. Pada sistem produksi, notifikasi persetujuan dikirim via WhatsApp resmi gedung.</span>
            </div>
            <div class="success-actions">
              <button type="button" class="btn-primary btn-primary--full" data-view-link="history">
                Lihat permohonan saya
                <svg><use href="#i-arrow-right"/></svg>
              </button>
              <button type="button" class="btn-ghost btn-ghost--full" data-view-link="home">Kembali ke beranda</button>
            </div>
          </div>
        </div>
      </section>

      <!-- HISTORY VIEW -->
      <section class="view" id="view-history" aria-labelledby="historyTitle">
        <div class="page-wrap page-wrap--narrow">
          <div class="page-header">
            <div>
              <p class="page-eyebrow">ARSIP DOKUMEN</p>
              <h1 id="historyTitle" class="page-title">Permohonan Saya</h1>
              <p class="page-subtitle">Riwayat permohonan izin yang diajukan pada sesi ini.</p>
            </div>
            <button type="button" class="btn-primary" data-start="loading">
              <svg><use href="#i-plus"/></svg>
              Permohonan baru
            </button>
          </div>
          <div id="historyList" class="history-list"></div>
        </div>
      </section>

      <!-- TRACK VIEW -->
      <section class="view" id="view-track" aria-labelledby="trackTitle">
        <div class="page-wrap page-wrap--narrow">
          <div class="page-header">
            <div>
              <p class="page-eyebrow">PELACAKAN STATUS</p>
              <h1 id="trackTitle" class="page-title">Cek Status Izin</h1>
              <p class="page-subtitle">Masukkan nomor referensi dan nomor WhatsApp yang digunakan saat mendaftar.</p>
            </div>
          </div>
          <form id="trackForm" class="form-section track-form">
            <div class="form-group">
              <label for="trackReference">Nomor referensi permohonan</label>
              <input id="trackReference" placeholder="Contoh: MBG-2026-LOG-123456" required>
            </div>
            <div class="form-group">
              <label for="trackPhone">Nomor WhatsApp penanggung jawab</label>
              <input id="trackPhone" type="tel" inputmode="tel" placeholder="Nomor yang didaftarkan" required>
            </div>
            <button class="btn-primary btn-primary--full" type="submit">
              Cari permohonan
              <svg><use href="#i-arrow-right"/></svg>
            </button>
          </form>
          <div id="trackResult" aria-live="polite"></div>
        </div>
      </section>

      <!-- HELP VIEW -->
      <section class="view" id="view-help" aria-labelledby="helpTitle">
        <div class="page-wrap page-wrap--narrow">
          <div class="page-header">
            <div>
              <p class="page-eyebrow">PANDUAN PENGGUNAAN</p>
              <h1 id="helpTitle" class="page-title">Bantuan &amp; Panduan</h1>
              <p class="page-subtitle">Ketentuan dan tata tertib perizinan operasional tenant di lingkungan Mal Bali Galeria.</p>
            </div>
          </div>

          <div class="help-accordion">
            <details class="help-item" open>
              <summary>
                <span>Berapa lama batas waktu pengajuan izin?</span>
                <svg><use href="#i-chevron-right"/></svg>
              </summary>
              <div class="help-body">
                <p>Pengajuan izin loading barang disarankan minimal H-1 sebelum jam 17.00 WITA. Untuk Surat Izin Kerja (SIK), pameran, dan event, pengajuan diajukan selambat-lambatnya H-3 hari kerja sebelum pelaksanaan.</p>
              </div>
            </details>

            <details class="help-item">
              <summary>
                <span>Kapan jam operasional loading dock?</span>
                <svg><use href="#i-chevron-right"/></svg>
              </summary>
              <div class="help-body">
                <p>Loading dock timur dan barat beroperasi pukul 22.00 - 08.00 WITA untuk barang besar, dan 08.00 - 10.00 WITA untuk pengiriman cepat / barang kecil. Di luar jam tersebut wajib mendapat izin dispensasi tertulis.</p>
              </div>
            </details>

            <details class="help-item">
              <summary>
                <span>Bagaimana alur persetujuan surat izin?</span>
                <svg><use href="#i-chevron-right"/></svg>
              </summary>
              <div class="help-body">
                <p>Setelah formulir dikirim, nomor referensi akan diterbitkan. Tim Building Management akan meninjau kelayakan teknis dan keselamatan kerja, lalu konfirmasi diteruskan ke tenant penanggung jawab.</p>
              </div>
            </details>
          </div>

          <div class="contact-card">
            <div>
              <h2>Pusat Informasi Tenant</h2>
              <p>Untuk koordinasi teknis mendesak atau kebutuhan izin khusus, kunjungi meja Building Management di Ground Floor Mal Bali Galeria.</p>
            </div>
          </div>
        </div>
      </section>

    </main>
  </div><!-- /.content-shell -->
</div><!-- /#appShell -->

<!-- Mobile Bottom Navigation -->
<nav class="mobile-nav" id="mobileNav" aria-label="Navigasi utama">
  <button type="button" class="mobile-nav-item active" data-view-link="home">
    <svg><use href="#i-grid"/></svg>
    <span>Beranda</span>
  </button>
  <button type="button" class="mobile-nav-item" data-view-link="history">
    <svg><use href="#i-file"/></svg>
    <span>Permohonan</span>
  </button>
  <button type="button" class="mobile-nav-fab" id="createShortcut" aria-label="Buat permohonan baru">
    <svg><use href="#i-plus"/></svg>
  </button>
  <button type="button" class="mobile-nav-item" data-view-link="track">
    <svg><use href="#i-search"/></svg>
    <span>Status</span>
  </button>
  <button type="button" class="mobile-nav-item" data-view-link="help">
    <svg><use href="#i-help"/></svg>
    <span>Bantuan</span>
  </button>
</nav>

<!-- Mobile Create Sheet -->
<div class="sheet-backdrop" id="sheetBackdrop" hidden></div>
<div class="create-sheet" id="createSheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle" hidden>
  <div class="sheet-drag-handle"></div>
  <div class="sheet-header">
    <div>
      <p class="sheet-eyebrow">PERMOHONAN BARU</p>
      <h2 id="sheetTitle">Pilih Jenis Izin</h2>
    </div>
    <button type="button" class="sheet-close-btn" id="closeSheet" aria-label="Tutup panel">
      <svg><use href="#i-plus"/></svg>
    </button>
  </div>
  <div class="sheet-list">
    <button type="button" class="sheet-option" data-start="loading">
      <div class="sheet-option-icon sheet-option-icon--blue"><svg><use href="#i-box"/></svg></div>
      <div class="sheet-option-label">
        <span>Loading &amp; Unloading Barang</span>
        <small>Logistik keluar-masuk gedung</small>
      </div>
      <svg class="sheet-option-chevron"><use href="#i-chevron-right"/></svg>
    </button>
    <button type="button" class="sheet-option" data-start="work">
      <div class="sheet-option-icon sheet-option-icon--violet"><svg><use href="#i-wrench"/></svg></div>
      <div class="sheet-option-label">
        <span>Surat Izin Kerja (SIK)</span>
        <small>Renovasi dan pekerjaan teknis</small>
      </div>
      <svg class="sheet-option-chevron"><use href="#i-chevron-right"/></svg>
    </button>
    <button type="button" class="sheet-option" data-start="exhibition">
      <div class="sheet-option-icon sheet-option-icon--rose"><svg><use href="#i-gallery"/></svg></div>
      <div class="sheet-option-label">
        <span>Surat Izin Pameran</span>
        <small>Display dan aktivasi brand</small>
      </div>
      <svg class="sheet-option-chevron"><use href="#i-chevron-right"/></svg>
    </button>
    <button type="button" class="sheet-option" data-start="event">
      <div class="sheet-option-icon sheet-option-icon--amber"><svg><use href="#i-calendar"/></svg></div>
      <div class="sheet-option-label">
        <span>Surat Izin Acara &amp; Kegiatan</span>
        <small>Event dan agenda khusus tenant</small>
      </div>
      <svg class="sheet-option-chevron"><use href="#i-chevron-right"/></svg>
    </button>
  </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/portal.js') }}"></script>
@endpush
