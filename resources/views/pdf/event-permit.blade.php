<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Surat Izin Event {{ $permit->permit_number }}</title>
  @include('pdf.partials.styles')
  <style>
    .event-notes { margin: 12px 0 0; padding: 10px 14px 10px 28px; border: 1px solid #d9e2ec; background: #f8fafc; color: #334155; font-size: 9px; line-height: 1.55; }
    .event-notes li { margin: 0 0 4px; }
    .event-needs { margin: 0; padding-left: 15px; }
    .event-needs li { margin-bottom: 3px; }
    .page-break { page-break-before: always; }
    .loading-window { margin: 14px 0; padding: 12px 16px; border: 1px solid #9bbbe7; background: #eef5ff; color: #173b70; font-size: 12px; text-align: center; }
  </style>
</head>
<body class="work-document">
  <img class="watermark" src="{{ $logoDataUri }}" alt="">
  <table class="header"><tr><td style="width:68px"><img class="header-logo" src="{{ $logoDataUri }}" alt="Mal Bali Galeria"></td><td class="brand-cell"><div class="brand-name">MAL BALI GALERIA</div><div class="brand-address">Jl. By Pass Ngurah Rai, Kuta, Badung, Bali 80361 &middot; Property Management</div></td><td class="document-meta" style="width:220px"><div class="document-kind">Surat Izin Event</div><div class="document-number">{{ $permit->permit_number }}</div></td></tr></table>
  <div class="approval">Dokumen telah disetujui secara digital oleh Divisi Event Promotion Mal Bali Galeria.</div>
  <h1 class="title">SURAT IZIN EVENT</h1>
  <p class="subtitle">{{ $permit->event_type_label }}</p>
  <p class="intro">Manajemen Mal Bali Galeria memberikan izin pelaksanaan event berdasarkan data dan ketentuan berikut.</p>
  <div class="validity"><strong>Masa berlaku:</strong> {{ $permit->start_date->copy()->locale('id')->translatedFormat('d F Y') }} sampai {{ $permit->end_date->copy()->locale('id')->translatedFormat('d F Y') }} &middot; {{ $permit->work_schedule_label }}.</div>

  <div class="section-title">Data Event</div>
  <table class="detail-table">
    <tr><th>Nama EO / Tenant</th><td>{{ $permit->contractor_name }}</td></tr>
    <tr><th>PIC / Penanggung Jawab</th><td>{{ $permit->applicant_name }} &middot; {{ $permit->applicant_phone }}</td></tr>
    <tr><th>Lokasi</th><td>{{ $permit->work_location }}</td></tr>
    <tr><th>Kategori</th><td>{{ $permit->work_category_label }}</td></tr>
    <tr><th>Jenis Pekerjaan</th><td>{{ $permit->work_type }}</td></tr>
    <tr><th>Rincian Pekerjaan</th><td>{{ $permit->notes }}</td></tr>
    <tr><th>Kebutuhan</th><td>@if(collect($permit->event_requirements)->isEmpty())Tidak ada kebutuhan tambahan@else<ul class="event-needs">@foreach($permit->event_requirements as $item)<li>{{ $item['group'] }}: {{ $item['name'] }}</li>@endforeach</ul>@endif</td></tr>
    <tr><th>Surat Loading Tambahan</th><td>{{ $permit->includes_loading_material ? 'Ya, terlampir pada halaman berikutnya' : 'Tidak' }}</td></tr>
    <tr><th>Disetujui Oleh</th><td>{{ $permit->reviewer?->name ?? 'Divisi Event Promotion' }}</td></tr>
    <tr><th>Tanggal Persetujuan</th><td>{{ $permit->reviewed_at?->copy()->locale('id')->translatedFormat('d F Y, H:i') }} WITA</td></tr>
  </table>

  <div class="section-title">Ketentuan</div>
  <ol class="event-notes">@foreach(config('event-permits.notes') as $note)<li>{{ $note }}</li>@endforeach</ol>
  @include('pdf.partials.verification', ['divisionLabel' => 'Event Promotion'])

  @if($permit->includes_loading_material)
    <div class="page-break"></div>
    <img class="watermark" src="{{ $logoDataUri }}" alt="">
    <table class="header"><tr><td style="width:68px"><img class="header-logo" src="{{ $logoDataUri }}" alt="Mal Bali Galeria"></td><td class="brand-cell"><div class="brand-name">MAL BALI GALERIA</div><div class="brand-address">Property Management</div></td><td class="document-meta" style="width:220px"><div class="document-kind">Surat Tambahan</div><div class="document-number">{{ $permit->permit_number }}</div></td></tr></table>
    <h1 class="title">IZIN LOADING / UNLOADING EVENT</h1>
    <p class="subtitle">Lampiran Surat Izin Event</p>
    <div class="loading-window"><strong>Estimasi waktu loading / unloading</strong><br>Pukul 22.00 sampai 21.00 WITA</div>
    <table class="detail-table"><tr><th>EO / Tenant</th><td>{{ $permit->contractor_name }}</td></tr><tr><th>PIC</th><td>{{ $permit->applicant_name }} &middot; {{ $permit->applicant_phone }}</td></tr><tr><th>Lokasi Event</th><td>{{ $permit->work_location }}</td></tr><tr><th>Periode</th><td>{{ $permit->start_date->copy()->locale('id')->translatedFormat('d F Y') }} sampai {{ $permit->end_date->copy()->locale('id')->translatedFormat('d F Y') }}</td></tr><tr><th>Keperluan</th><td>Loading / unloading material dan perlengkapan {{ $permit->work_category_label }}</td></tr></table>
    <p class="closing">Seluruh aktivitas loading / unloading wajib mengikuti arahan petugas keamanan dan tidak mengganggu operasional serta kenyamanan pengunjung Mal Bali Galeria.</p>
    @include('pdf.partials.verification', ['divisionLabel' => 'Event Promotion'])
  @endif
</body>
</html>
