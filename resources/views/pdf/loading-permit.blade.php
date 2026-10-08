<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Surat Izin Loading {{ $permit->permit_number }}</title>
  @include('pdf.partials.styles')
</head>
<body class="loading-document">
  <img class="watermark" src="{{ $logoDataUri }}" alt="">

  <table class="header">
    <tr>
      <td style="width:68px"><img class="header-logo" src="{{ $logoDataUri }}" alt="Mal Bali Galeria"></td>
      <td class="brand-cell">
        <div class="brand-name">MAL BALI GALERIA</div>
        <div class="brand-address">Jl. By Pass Ngurah Rai, Kuta, Badung, Bali 80361 &middot; Property Management</div>
      </td>
      <td class="document-meta" style="width:220px">
        <div class="document-kind">Surat Izin Loading / Unloading</div>
        <div class="document-number">{{ $permit->permit_number }}</div>
      </td>
    </tr>
  </table>

  <div class="approval">Dokumen telah disetujui secara digital oleh Divisi Tenant Relationship Mal Bali Galeria.</div>
  <h1 class="title">SURAT IZIN LOADING / UNLOADING BARANG</h1>
  <p class="subtitle">Dokumen operasional tenant Mal Bali Galeria</p>

  <p class="intro">Manajemen Mal Bali Galeria memberikan izin pelaksanaan kegiatan loading atau unloading barang berdasarkan data permohonan berikut.</p>
  <div class="validity"><strong>Masa berlaku:</strong> {{ $permit->start_date->copy()->locale('id')->translatedFormat('d F Y') }} sampai {{ $permit->end_date->copy()->locale('id')->translatedFormat('d F Y') }}, pukul {{ $permit->movement_time_label }}.</div>

  <div class="section-title">Data Permohonan</div>
  <table class="detail-table">
    <tr><th>Nama Tenant</th><td>{{ $permit->tenant_name }}</td></tr>
    <tr><th>Penanggung Jawab</th><td>{{ $permit->applicant_name }} &middot; {{ $permit->applicant_phone }}</td></tr>
    <tr><th>Arah Pergerakan</th><td>{{ $permit->direction_label }}</td></tr>
    <tr><th>Periode</th><td>{{ $permit->start_date->copy()->locale('id')->translatedFormat('d F Y') }} s.d. {{ $permit->end_date->copy()->locale('id')->translatedFormat('d F Y') }}</td></tr>
    <tr><th>{{ $permit->movement_time_field_label }}</th><td>{{ $permit->movement_time_label }}</td></tr>
    <tr><th>Barang</th><td>{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}@if($permit->item_description) &middot; {{ $permit->item_description }}@endif</td></tr>
    @if($permit->vehicle_plate)<tr><th>Nomor Kendaraan</th><td>{{ $permit->vehicle_plate }}</td></tr>@endif
    <tr><th>Disetujui Oleh</th><td>{{ $permit->reviewer?->name ?? 'Divisi Tenant Relationship' }}</td></tr>
    <tr><th>Tanggal Persetujuan</th><td>{{ $permit->reviewed_at?->copy()->locale('id')->translatedFormat('d F Y, H:i') }} WITA</td></tr>
  </table>

  @if($permit->review_notes)<div class="notes"><strong>Catatan validator:</strong> {{ $permit->review_notes }}</div>@endif
  <p class="closing">Surat ini wajib ditunjukkan kepada petugas keamanan sebelum memasuki area loading. Petugas dapat memindai QR untuk memeriksa keaslian dan masa berlaku dokumen.</p>

  <table class="verification">
    <tr>
      <td>
        <img class="qr-image" src="{{ $qrDataUri }}" alt="QR verifikasi">
        <div class="qr-copy">Pindai untuk memverifikasi dokumen. Setiap surat memiliki kode verifikasi yang unik.</div>
      </td>
      <td class="signature">
        <div class="signature-date">Kuta, {{ ($permit->reviewed_at ?? now())->copy()->locale('id')->translatedFormat('d F Y') }}</div>
        <div class="signature-line">Divisi Tenant Relationship</div>
        <div class="signature-role">Mal Bali Galeria</div>
      </td>
    </tr>
  </table>

  <footer class="document-footer">&copy; {{ now()->year }} Mal Bali Galeria &middot; Property Management &middot; Dikembangkan oleh Yogi Prayoga</footer>
</body>
</html>
