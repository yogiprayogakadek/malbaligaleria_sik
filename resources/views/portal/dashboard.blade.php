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
      <a href="{{ route('work-permits.create') }}" class="service-card">
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

  </div>
</section>
@endsection
