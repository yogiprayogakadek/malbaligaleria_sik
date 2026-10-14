<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Laporan Perizinan</title>
  <style>
    @page { margin: 24px 26px 30px; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #1d2939; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
    header { padding-bottom: 12px; border-bottom: 2px solid #1f5c9e; }
    header table { width: 100%; border-collapse: collapse; }
    header img { width: 48px; height: 48px; object-fit: contain; }
    h1 { margin: 0; color: #173e70; font-size: 16px; }
    header p { margin: 4px 0 0; color: #667085; font-size: 8px; }
    .generated { text-align: right; }
    .summary { width: 100%; margin: 14px 0; border-collapse: separate; border-spacing: 6px 0; }
    .summary td { width: 25%; padding: 9px 10px; border: 1px solid #d9e2ec; background: #f7f9fc; }
    .summary span, .summary strong { display: block; }
    .summary span { color: #667085; font-size: 7px; }
    .summary strong { margin-top: 3px; color: #173e70; font-size: 14px; }
    .data { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .data th { padding: 6px 5px; color: #fff; background: #1f5c9e; text-align: left; font-size: 7px; }
    .data td { padding: 5px; border: 1px solid #dde4ec; vertical-align: top; line-height: 1.35; word-wrap: break-word; }
    .data tr:nth-child(even) td { background: #f8fafc; }
    .number { width: 3%; text-align: center; }
    .submitted { width: 9%; }
    .permit { width: 16%; }
    .entity { width: 17%; }
    .applicant { width: 14%; }
    .type { width: 14%; }
    .division { width: 6%; text-align: center; }
    .period { width: 11%; }
    .status { width: 10%; }
    footer { position: fixed; right: 0; bottom: -18px; left: 0; color: #8592a3; text-align: center; font-size: 7px; }
  </style>
</head>
<body>
  <header>
    <table><tr><td style="width:58px">@if($logoData)<img src="{{ $logoData }}" alt="">@endif</td><td><h1>Laporan Perizinan Mal Bali Galeria</h1><p>Periode {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }} s.d. {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y') }} &middot; {{ $summary['total'] }} permohonan</p></td><td class="generated"><strong>Dibuat</strong><p>{{ $generatedAt->format('d M Y, H:i') }} WITA</p></td></tr></table>
  </header>

  <table class="summary"><tr><td><span>Total permohonan</span><strong>{{ number_format($summary['total']) }}</strong></td><td><span>Masih diproses</span><strong>{{ number_format($summary['active']) }}</strong></td><td><span>Disetujui</span><strong>{{ number_format($summary['approved']) }}</strong></td><td><span>Ditolak</span><strong>{{ number_format($summary['rejected']) }}</strong></td></tr></table>

  <table class="data">
    <thead><tr><th class="number">No.</th><th class="submitted">Diajukan</th><th class="permit">Nomor surat</th><th class="entity">Tenant / kontraktor</th><th class="applicant">Pemohon</th><th class="type">Jenis</th><th class="division">Divisi</th><th class="period">Periode izin</th><th class="status">Status</th></tr></thead>
    <tbody>
      @forelse($rows as $row)
        <tr><td class="number">{{ $loop->iteration }}</td><td>{{ $row->created_at->format('d/m/Y H:i') }}</td><td>{{ $row->permit_number }}</td><td>{{ $row->entity_name }}</td><td>{{ $row->applicant_name }}</td><td>{{ $row->type_label }}<br>{{ $row->subtype_label }}</td><td class="division">{{ $row->assigned_division }}</td><td>{{ $row->start_date->format('d/m/Y') }} - {{ $row->end_date->format('d/m/Y') }}</td><td>{{ $row->status_label }}</td></tr>
      @empty
        <tr><td colspan="9" style="padding:20px;text-align:center">Tidak ada data pada periode dan filter yang dipilih.</td></tr>
      @endforelse
    </tbody>
  </table>

  <footer>&copy; {{ $generatedAt->year }} Mal Bali Galeria &middot; Laporan internal &middot; Yogi Prayoga</footer>
</body>
</html>
