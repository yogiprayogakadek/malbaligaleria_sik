@extends('layouts.portal')

@section('title', 'Bantuan & Panduan : Mal Bali Galeria')
@section('page-title', 'Bantuan')

@section('content')
<section class="view active" id="view-help" aria-labelledby="helpTitle">
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
          <p>Pengajuan izin loading barang disarankan minimal H-1 sebelum pukul 17.00 WITA. Untuk Surat Izin Kerja (SIK), pameran, dan event, pengajuan diajukan selambat-lambatnya H-3 hari kerja sebelum pelaksanaan.</p>
        </div>
      </details>

      <details class="help-item">
        <summary>
          <span>Kapan jam operasional loading dock?</span>
          <svg><use href="#i-chevron-right"/></svg>
        </summary>
        <div class="help-body">
          <p>Loading dock timur dan barat beroperasi pukul 22.00 - 08.00 WITA untuk barang besar/material, dan 08.00 - 10.00 WITA untuk pengiriman cepat / barang kecil. Di luar jam tersebut wajib mendapat izin dispensasi tertulis.</p>
        </div>
      </details>

      <details class="help-item">
        <summary>
          <span>Bagaimana alur persetujuan surat izin?</span>
          <svg><use href="#i-chevron-right"/></svg>
        </summary>
        <div class="help-body">
          <p>Setelah formulir dikirim, nomor referensi akan diterbitkan. Tim Building Management akan meninjau kelayakan teknis dan keselamatan kerja, lalu konfirmasi diteruskan ke penanggung jawab tenant.</p>
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
@endsection
