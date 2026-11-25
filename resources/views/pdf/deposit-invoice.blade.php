<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Invoice Deposit {{ $invoiceNumber }}</title>
  <style>
    @page { margin: 30px 34px; }
    body { color: #0f172a; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; }
    table { width: 100%; border-collapse: collapse; }
    .header { border-bottom: 2px solid #2563eb; padding-bottom: 14px; }
    .logo { width: 58px; }
    .brand { padding-left: 12px; }
    .brand strong { display: block; font-size: 17px; }
    .meta { text-align: right; }
    .meta strong { display: block; font-size: 13px; }
    h1 { margin: 30px 0 4px; font-size: 22px; }
    .lead { margin: 0 0 20px; color: #475569; }
    .details th, .details td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; vertical-align: top; }
    .details th { width: 32%; background: #f1f5f9; }
    .amount { margin: 20px 0; padding: 15px; border: 1px solid #93c5fd; background: #eff6ff; }
    .amount span { display: block; color: #475569; }
    .amount strong { display: block; margin-top: 3px; color: #1d4ed8; font-size: 22px; }
    h2 { margin: 22px 0 8px; font-size: 14px; }
    .accounts td { border-bottom: 1px solid #e2e8f0; padding: 9px 4px; }
    .accounts strong, .accounts span { display: block; }
    .accounts span { color: #475569; }
    .note { margin-top: 22px; padding: 12px; border: 1px solid #cbd5e1; background: #f8fafc; }
    .footer { margin-top: 28px; color: #64748b; font-size: 9px; }
  </style>
</head>
<body>
  <table class="header"><tr><td style="width:70px"><img class="logo" src="{{ $logoDataUri }}" alt=""></td><td class="brand"><strong>MAL BALI GALERIA</strong><span>Property Management</span></td><td class="meta"><strong>{{ $invoiceNumber }}</strong><span>Diterbitkan {{ $issuedAt->format('d M Y') }}</span></td></tr></table>
  <h1>Invoice Security Deposit</h1>
  <p class="lead">Tagihan deposit untuk pelaksanaan pekerjaan di Mal Bali Galeria.</p>
  <table class="details">
    <tr><th>Referensi izin</th><td>{{ $permit->permit_number }}</td></tr>
    <tr><th>Tenant / kontraktor</th><td>{{ collect([$permit->tenant_name, $permit->contractor_name])->filter()->implode(' / ') }}</td></tr>
    <tr><th>Penanggung jawab</th><td>{{ $permit->applicant_name }}</td></tr>
    <tr><th>Pekerjaan</th><td>{{ $permit->work_type }}</td></tr>
    <tr><th>Lokasi</th><td>{{ $permit->work_location }}</td></tr>
    <tr><th>Periode</th><td>{{ $permit->start_date->format('d M Y') }} sampai {{ $permit->end_date->format('d M Y') }}</td></tr>
    <tr><th>Jatuh tempo</th><td>{{ \Carbon\Carbon::parse($dueDate)->format('d M Y') }}</td></tr>
  </table>
  <div class="amount"><span>Jumlah security deposit</span><strong>Rp {{ number_format((float) $permit->deposit_amount, 0, ',', '.') }}</strong></div>
  <h2>Rekening Pembayaran</h2>
  <table class="accounts">
    @foreach($accounts as $account)<tr><td><strong>{{ $account->bank_name }}</strong><span>{{ $account->account_number }}</span></td><td><span>Atas nama</span><strong>{{ $account->account_holder }}</strong></td></tr>@endforeach
  </table>
  @if($setting->payment_instructions)<div class="note"><strong>Instruksi pembayaran</strong><br>{{ $setting->payment_instructions }}</div>@endif
  <p class="footer">Cantumkan nomor invoice pada keterangan pembayaran. Pembayaran akan diverifikasi oleh Finance sebelum izin kerja diterbitkan.</p>
</body>
</html>
