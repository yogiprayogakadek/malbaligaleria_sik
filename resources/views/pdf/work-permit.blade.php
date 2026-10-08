<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Surat Izin Kerja {{ $permit->permit_number }}</title>
  @include('pdf.partials.styles')
</head>
<body class="work-document">
  <img class="watermark" src="{{ $logoDataUri }}" alt="">

  <table class="header">
    <tr>
      <td style="width:68px"><img class="header-logo" src="{{ $logoDataUri }}" alt="Mal Bali Galeria"></td>
      <td class="brand-cell">
        <div class="brand-name">MAL BALI GALERIA</div>
        <div class="brand-address">Jl. By Pass Ngurah Rai, Kuta, Badung, Bali 80361 &middot; Property Management</div>
      </td>
      <td class="document-meta" style="width:220px">
        <div class="document-kind">Surat Izin Kerja</div>
        <div class="document-number">{{ $permit->permit_number }}</div>
      </td>
    </tr>
  </table>

  <div class="approval">Dokumen telah disetujui secara digital oleh Divisi {{ $permit->assigned_division }} Mal Bali Galeria.</div>
  <h1 class="title">SURAT IZIN KERJA</h1>
  <p class="subtitle">Toko / roof / basement / parkir area</p>

  <p class="intro">Manajemen Mal Bali Galeria memberikan izin pelaksanaan pekerjaan berdasarkan data dan ketentuan berikut.</p>
  <div class="validity"><strong>Masa berlaku:</strong> {{ $permit->start_date->copy()->locale('id')->translatedFormat('d F Y') }} sampai {{ $permit->end_date->copy()->locale('id')->translatedFormat('d F Y') }} &middot; {{ $permit->work_schedule_label }}.</div>

  <div class="section-title">Data Pekerjaan</div>
  <table class="detail-table">
    <tr><th>Kontraktor / Tenant</th><td>{{ $permit->contractor_name }}</td></tr>
    <tr><th>Penanggung Jawab</th><td>{{ $permit->applicant_name }} &middot; {{ $permit->applicant_phone }}</td></tr>
    <tr><th>Kategori Pekerjaan</th><td>{{ $permit->work_category_label }}</td></tr>
    <tr><th>Jenis Pekerjaan</th><td>{{ $permit->work_type }}</td></tr>
    <tr><th>Lokasi Pekerjaan</th><td>{{ $permit->work_location }}</td></tr>
    <tr><th>Waktu Kerja</th><td>{{ $permit->work_schedule_label }}</td></tr>
    <tr><th>Kebutuhan Tambahan</th><td>{{ $permit->needs_water ? 'Air' : 'Tidak ada' }}@if($permit->deposit_required) &middot; Security deposit Rp {{ number_format((float) $permit->deposit_amount, 0, ',', '.') }}@endif</td></tr>
    <tr><th>Disetujui Oleh</th><td>{{ $permit->reviewer?->name ?? 'Divisi '.$permit->assigned_division }}</td></tr>
    <tr><th>Tanggal Persetujuan</th><td>{{ $permit->reviewed_at?->copy()->locale('id')->translatedFormat('d F Y, H:i') }} WITA</td></tr>
  </table>

  <div class="section-title">Daftar Pekerja</div>
  <table class="worker-table">
    <thead><tr><th class="number">No.</th><th>Nama Pekerja</th><th class="identity">Nomor ID</th></tr></thead>
    <tbody>
      @foreach($permit->workers as $worker)
        <tr><td class="number">{{ $loop->iteration }}</td><td>{{ $worker->name }}</td><td>{{ $worker->identity_number ?: '-' }}</td></tr>
      @endforeach
    </tbody>
  </table>

  @if($permit->notes || $permit->review_notes)<div class="notes"><strong>Catatan:</strong> {{ collect([$permit->notes, $permit->review_notes])->filter()->implode(' | ') }}</div>@endif
  <p class="closing">Seluruh pekerja wajib membawa identitas, menggunakan alat pelindung yang sesuai, menjaga kebersihan area, serta mengikuti arahan petugas Mal Bali Galeria.</p>

  <table class="verification">
    <tr>
      <td>
        <img class="qr-image" src="{{ $qrDataUri }}" alt="QR verifikasi">
        <div class="qr-copy">Pindai untuk memverifikasi dokumen dan masa berlaku izin kerja.</div>
      </td>
      <td class="signature">
        <div class="signature-date">Kuta, {{ ($permit->reviewed_at ?? now())->copy()->locale('id')->translatedFormat('d F Y') }}</div>
        <div class="signature-line">Divisi {{ $permit->assigned_division }}</div>
        <div class="signature-role">Mal Bali Galeria</div>
      </td>
    </tr>
  </table>

  <footer class="document-footer">&copy; {{ now()->year }} Mal Bali Galeria &middot; Property Management &middot; Dikembangkan oleh Yogi Prayoga</footer>
</body>
</html>
