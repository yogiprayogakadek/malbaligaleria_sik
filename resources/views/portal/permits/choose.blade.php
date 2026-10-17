@extends('layouts.portal')

@section('title', 'Pilih Jenis Permohonan : Mal Bali Galeria')
@section('page-title', 'Permohonan Baru')

@section('content')
<section class="view active" aria-labelledby="permitChoiceTitle">
  <div class="page-wrap permit-choice-page">
    <div class="page-header">
      <div>
        <p class="page-eyebrow">PERMOHONAN BARU</p>
        <h1 id="permitChoiceTitle" class="page-title">Pilih jenis surat</h1>
        <p class="page-subtitle">Pilih layanan sesuai kegiatan yang akan dilakukan di area Mal Bali Galeria.</p>
      </div>
      <a href="{{ url()->previous() }}" class="btn-secondary"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
    </div>

    <div class="permit-choice-list">
      <a href="{{ route('loading.create') }}" class="permit-choice-item permit-choice-item--loading">
        <span class="permit-choice-icon"><svg><use href="#i-box"/></svg></span>
        <span class="permit-choice-copy"><small>LOGISTIK</small><strong>Loading &amp; Unloading Barang</strong><span>Perpindahan barang, material, dan logistik masuk atau keluar gedung.</span></span>
        <svg class="permit-choice-arrow"><use href="#i-arrow-right"/></svg>
      </a>
      <a href="{{ route('work-permits.create') }}" class="permit-choice-item permit-choice-item--work">
        <span class="permit-choice-icon"><svg><use href="#i-wrench"/></svg></span>
        <span class="permit-choice-copy"><small>OPERASIONAL</small><strong>Surat Izin Kerja</strong><span>Pekerjaan teknis, perawatan, renovasi, dan aktivitas kontraktor.</span></span>
        <svg class="permit-choice-arrow"><use href="#i-arrow-right"/></svg>
      </a>
    </div>
  </div>
</section>
@endsection
