@extends('layouts.portal')

@section('title', 'Mal Bali Galeria : Beranda Portal Izin')
@section('page-title', 'Beranda')

@section('content')
<section class="view active" id="view-home" aria-labelledby="home-title">
  <div class="page-wrap">
    <div class="page-header">
      <div>
        <p class="page-eyebrow">Portal Perizinan Tenant</p>
        <h1 id="home-title" class="page-title">Selamat datang di MBG</h1>
        <p class="page-subtitle">Ajukan surat izin operasional gedung Mal Bali Galeria secara mudah dan terstruktur.</p>
      </div>
      <a href="{{ route('loading.create') }}" class="btn-primary">
        <svg><use href="#i-plus"/></svg>
        Permohonan baru
      </a>
    </div>

    <div class="section-label">LAYANAN PERIZINAN</div>
    <div class="service-grid">

      <!-- 1. Loading & Unloading -->
      <a href="{{ route('loading.create') }}" class="service-card">
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
      </a>

      <!-- 2. Surat Izin Kerja -->
      <a href="{{ route('permits.create', ['type' => 'work']) }}" class="service-card">
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
      </a>

      <!-- 3. Surat Izin Pameran -->
      <a href="{{ route('permits.create', ['type' => 'exhibition']) }}" class="service-card">
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
      </a>

      <!-- 4. Surat Izin Event -->
      <a href="{{ route('permits.create', ['type' => 'event']) }}" class="service-card">
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
      </a>

    </div>

    <!-- Info Strip -->
    <div class="info-strip">
      <div class="info-strip-icon">
        <svg><use href="#i-shield-check"/></svg>
      </div>
      <div class="info-strip-body">
        <strong>Prosedur tanpa cetak dokumen</strong>
        <p>Isi formulir, simpan nomor referensi, dan pantau status izin kapan saja.</p>
      </div>
      <a href="{{ route('portal.help') }}" class="info-strip-action" aria-label="Baca panduan">
        <svg><use href="#i-chevron-right"/></svg>
      </a>
    </div>

    <footer class="page-footer">
      &copy; 2026 Mal Bali Galeria &middot; Property Management
    </footer>
  </div>
</section>
@endsection
