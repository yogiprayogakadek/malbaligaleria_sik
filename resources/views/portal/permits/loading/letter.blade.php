<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Surat Izin Loading {{ $permit->permit_number }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      font-size: 13px;
      background: #f8fafc;
      color: #0f172a;
    }
    .letter-page {
      max-width: 794px;
      margin: 24px auto;
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 4px 24px rgba(15,23,42,.08);
    }
    /* Header */
    .letter-header {
      background: #0f172a;
      color: #fff;
      padding: 24px 36px;
      display: flex;
      align-items: center;
      gap: 20px;
    }
    .letter-header-logo { width: 64px; }
    .letter-header-text h1 { font-size: 14px; font-weight: 700; letter-spacing: .02em; }
    .letter-header-text p { font-size: 11px; color: #94a3b8; margin-top: 2px; }
    .letter-header-divider { margin-left: auto; border-left: 1px solid rgba(255,255,255,.15); padding-left: 20px; text-align: right; }
    .letter-header-divider .letter-type { font-size: 10px; color: #93c5fd; text-transform: uppercase; letter-spacing: .08em; }
    .letter-header-divider .letter-number { font-size: 15px; font-weight: 700; margin-top: 2px; }

    /* Status ribbon */
    .letter-ribbon {
      background: #dcfce7;
      border-bottom: 2px solid #86efac;
      padding: 10px 36px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12px;
      font-weight: 600;
      color: #15803d;
    }
    .letter-ribbon svg { width: 16px; height: 16px; flex: none; }

    /* Body */
    .letter-body { padding: 32px 36px; }
    .letter-subject { font-size: 15px; font-weight: 700; margin-bottom: 20px; }
    .letter-opener { line-height: 1.8; color: #334155; margin-bottom: 24px; }

    /* Detail table */
    .letter-table { width: 100%; border-collapse: collapse; margin-bottom: 28px; }
    .letter-table th, .letter-table td {
      text-align: left;
      padding: 8px 12px;
      border: 1px solid #e2e8f0;
      font-size: 12.5px;
    }
    .letter-table th { background: #f8fafc; font-weight: 600; width: 36%; }
    .letter-table tr:nth-child(even) td { background: #fafafa; }

    /* QR + signature section */
    .letter-footer-row {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      margin-top: 32px;
      padding-top: 24px;
      border-top: 1px solid #e2e8f0;
      gap: 24px;
    }
    .letter-qr-block { text-align: center; }
    .letter-qr-block img { width: 120px; height: 120px; display: block; margin: 0 auto; }
    .letter-qr-label {
      font-size: 10px;
      color: #64748b;
      margin-top: 6px;
      max-width: 130px;
      line-height: 1.4;
    }
    .letter-signature { text-align: center; }
    .letter-signature-place { font-size: 12px; color: #64748b; margin-bottom: 64px; }
    .letter-signature-name { font-size: 13px; font-weight: 700; border-top: 1px solid #94a3b8; padding-top: 6px; margin-top: 4px; }
    .letter-signature-title { font-size: 11px; color: #64748b; }
    .letter-credit { padding: 12px 36px; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 9px; text-align: center; }

    /* Validity bar */
    .letter-validity {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 6px;
      padding: 10px 14px;
      display: flex;
      gap: 10px;
      align-items: center;
      font-size: 12px;
      color: #92400e;
      margin-bottom: 28px;
    }

    /* Print */
    @media print {
      body { background: white; }
      .letter-page { border: none; box-shadow: none; margin: 0; border-radius: 0; }
      .no-print { display: none !important; }
    }

    /* Actions bar */
    .letter-actions {
      position: sticky;
      bottom: 0;
      background: #0f172a;
      padding: 14px 24px;
      display: flex;
      justify-content: center;
      gap: 12px;
    }
    .letter-actions .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 20px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      border: none;
      text-decoration: none;
      transition: background .15s;
    }
    .btn-print { background: #3b82f6; color: #fff; }
    .btn-print:hover { background: #2563eb; }
    .btn-back { background: rgba(255,255,255,.1); color: #e2e8f0; }
    .btn-back:hover { background: rgba(255,255,255,.18); }
  </style>
</head>
<body>

<div class="letter-actions no-print">
  <button class="btn btn-print" onclick="window.print()">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><use href="#ip-printer"/></svg>
    Cetak Surat
  </button>
  <a class="btn btn-back" href="javascript:history.back()">Kembali</a>
</div>

<div class="letter-page">

  {{-- Header --}}
  <div class="letter-header">
    <img src="{{ asset('logo.png') }}" alt="Mal Bali Galeria" class="letter-header-logo">
    <div class="letter-header-text">
      <h1>MAL BALI GALERIA</h1>
      <p>Jl. Sunset Road, Kuta, Badung, Bali : Manajemen Properti</p>
    </div>
    <div class="letter-header-divider">
      <div class="letter-type">Surat Izin Loading</div>
      <div class="letter-number">{{ $permit->permit_number }}</div>
    </div>
  </div>

  {{-- Approved ribbon --}}
  <div class="letter-ribbon">
    <svg fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
      <polyline points="20 6 9 17 4 12"/>
    </svg>
    Surat ini telah disetujui dan ditandatangani secara digital oleh tim Tenant Relationship Mal Bali Galeria.
  </div>

  <div class="letter-body">
    <div class="letter-subject">Surat Izin Kegiatan Loading &amp; Unloading Barang</div>

    <p class="letter-opener">
      Menindaklanjuti permohonan dari tenant yang bersangkutan, dengan ini Manajemen Mal Bali Galeria
      memberikan izin kegiatan loading/unloading barang dengan rincian sebagai berikut:
    </p>

    {{-- Validity --}}
    <div class="letter-validity">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
      Surat ini berlaku hingga: <strong>{{ $permit->barcode_expires_at?->format('d M Y, H:i') }} WIB</strong>
    </div>

    {{-- Detail Table --}}
    <table class="letter-table">
      <tbody>
        <tr><th>Nomor Surat</th><td><strong>{{ $permit->permit_number }}</strong></td></tr>
        <tr><th>Nama Tenant</th><td>{{ $permit->tenant_name }}</td></tr>
        <tr><th>Penanggung Jawab</th><td>{{ $permit->applicant_name }}</td></tr>
        <tr><th>Nomor HP / WA</th><td>{{ $permit->applicant_phone }}</td></tr>
        <tr><th>Arah Pergerakan</th><td>{{ $permit->direction_label }}</td></tr>
        <tr><th>Tanggal Mulai</th><td>{{ $permit->start_date->format('d F Y') }}</td></tr>
        <tr><th>Tanggal Selesai</th><td>{{ $permit->end_date->format('d F Y') }}</td></tr>
        <tr><th>Jumlah Barang</th><td>{{ $permit->item_count }} {{ $permit->item_unit ?? 'pcs' }}</td></tr>
        <tr><th>Keterangan Barang</th><td>{{ $permit->item_description ?: '-' }}</td></tr>
        @if($permit->vehicle_plate)
          <tr><th>No. Kendaraan</th><td>{{ $permit->vehicle_plate }}</td></tr>
        @endif
        <tr><th>Disetujui Oleh</th><td>{{ $permit->reviewer?->name ?? 'Divisi TR' }}</td></tr>
        <tr><th>Tanggal Persetujuan</th><td>{{ $permit->reviewed_at?->format('d F Y, H:i') }} WIB</td></tr>
        @if($permit->review_notes)
          <tr><th>Catatan</th><td>{{ $permit->review_notes }}</td></tr>
        @endif
      </tbody>
    </table>

    {{-- QR + Signature --}}
    <div class="letter-footer-row">
      <div class="letter-qr-block">
        {!! QrCode::size(120)->generate(route('scanner.verify', ['token' => $permit->barcode_token])) !!}
        <div class="letter-qr-label">
          Scan QR ini untuk verifikasi keaslian surat. Barcode unik, satu surat satu kode.
        </div>
      </div>

      <div style="flex:1; text-align:center;">
        <p style="font-size:12px; color:#64748b; line-height:1.7; max-width:260px; margin:0 auto;">
          Harap tunjukkan surat ini kepada petugas keamanan saat memasuki area loading.
          Petugas akan melakukan scan QR untuk konfirmasi.
        </p>
      </div>

      <div class="letter-signature">
        <div class="letter-signature-place">Kuta, {{ now()->format('d F Y') }}</div>
        <div class="letter-signature-name">Divisi Tenant Relationship</div>
        <div class="letter-signature-title">Mal Bali Galeria</div>
      </div>
    </div>

  </div>

  <footer class="letter-credit">&copy; {{ now()->year }} Mal Bali Galeria &middot; Property Management &middot; Dikembangkan oleh Yogi Prayoga</footer>
</div>

</body>
</html>
