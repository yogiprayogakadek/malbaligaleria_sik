<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Invoice Deposit {{ $invoiceNumber }}</title>
  <style>
    @page { margin: 28px 34px 26px; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.42; }
    table { width: 100%; border-collapse: collapse; }
    .masthead { border-bottom: 3px solid #193a67; padding-bottom: 13px; }
    .logo-cell { width: 64px; vertical-align: middle; }
    .logo { display: block; width: 54px; height: auto; }
    .brand { vertical-align: middle; }
    .brand-name { display: block; color: #132a4a; font-size: 16px; font-weight: 700; letter-spacing: .02em; }
    .brand-unit { display: block; margin-top: 2px; color: #5a6678; font-size: 9px; }
    .invoice-heading { width: 225px; text-align: right; vertical-align: middle; }
    .invoice-heading span { display: block; color: #687487; font-size: 8px; font-weight: 700; letter-spacing: .12em; }
    .invoice-heading strong { display: block; margin-top: 3px; color: #132a4a; font-size: 14px; }
    .invoice-heading small { display: block; margin-top: 2px; color: #687487; font-size: 8px; }
    .title-row { margin: 19px 0 13px; }
    .title-row td { vertical-align: bottom; }
    h1 { margin: 0; color: #132a4a; font-size: 24px; line-height: 1.15; }
    .subtitle { margin: 4px 0 0; color: #687487; font-size: 9px; }
    .status { display: inline-block; padding: 5px 9px; border: 1px solid #a9c2df; color: #193a67; background: #edf4fb; font-size: 8px; font-weight: 700; letter-spacing: .05em; }
    .summary { margin-bottom: 15px; border: 1px solid #cad5e2; }
    .summary td { padding: 12px 14px; vertical-align: middle; }
    .summary .amount-cell { width: 60%; background: #132a4a; color: #fff; }
    .summary span { display: block; color: #6b7789; font-size: 8px; font-weight: 700; letter-spacing: .06em; }
    .summary .amount-cell span { color: #bac9da; }
    .summary strong { display: block; margin-top: 4px; font-size: 18px; line-height: 1.2; }
    .summary .due-cell strong { color: #172033; font-size: 12px; }
    .section-title { margin: 0 0 7px; color: #193a67; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .two-column { margin-bottom: 15px; }
    .two-column > tbody > tr > td { width: 50%; vertical-align: top; }
    .two-column > tbody > tr > td:first-child { padding-right: 7px; }
    .two-column > tbody > tr > td:last-child { padding-left: 7px; }
    .panel { border: 1px solid #d6dee8; }
    .data td { padding: 7px 9px; border-bottom: 1px solid #e5eaf0; vertical-align: top; overflow-wrap: break-word; }
    .data tr:last-child td { border-bottom: 0; }
    .data .label { width: 35%; color: #6b7789; font-size: 8px; }
    .data .value { color: #172033; font-weight: 700; }
    .data .value-address { font-weight: 400; line-height: 1.45; }
    .accounts { margin-bottom: 14px; border: 1px solid #d6dee8; }
    .accounts th { padding: 7px 9px; background: #f1f5f9; color: #596579; font-size: 8px; text-align: left; }
    .accounts td { padding: 8px 9px; border-top: 1px solid #e5eaf0; vertical-align: top; }
    .accounts td strong { display: block; color: #172033; }
    .instruction { margin-bottom: 14px; padding: 10px 12px; border: 1px solid #c8d6e6; background: #f5f8fb; }
    .instruction strong { display: block; margin-bottom: 3px; color: #193a67; }
    .instruction p { margin: 0; color: #46546a; white-space: pre-line; }
    .authorization { page-break-inside: avoid; border-top: 1px solid #cad5e2; padding-top: 13px; }
    .authorization-copy { padding-right: 18px; vertical-align: top; }
    .authorization-copy strong { display: block; color: #193a67; font-size: 10px; }
    .authorization-copy p { margin: 4px 0 0; color: #687487; font-size: 8px; line-height: 1.5; }
    .signature-cell { width: 230px; text-align: center; vertical-align: top; }
    .signature-label { display: block; color: #6b7789; font-size: 8px; }
    .signature-image { display: block; max-width: 116px; max-height: 66px; margin: 6px auto 5px; }
    .signature-image.qr { width: 76px; height: 76px; max-height: 76px; }
    .issuer-name { display: block; padding-top: 4px; border-top: 1px solid #8591a2; color: #172033; font-size: 9px; font-weight: 700; }
    .issuer-title { display: block; margin-top: 2px; color: #687487; font-size: 8px; }
    .document-id { display: block; margin-top: 4px; color: #7b8798; font-size: 6.5px; word-break: break-all; }
    .footer { position: fixed; right: 0; bottom: 0; left: 0; border-top: 1px solid #d6dee8; padding-top: 6px; color: #7b8798; font-size: 7px; }
    .footer td:last-child { text-align: right; }
  </style>
</head>
<body>
  <table class="masthead">
    <tr>
      <td class="logo-cell"><img class="logo" src="{{ $logoDataUri }}" alt=""></td>
      <td class="brand"><span class="brand-name">MAL BALI GALERIA</span><span class="brand-unit">Portal Administrasi Perizinan</span></td>
      <td class="invoice-heading"><span>NOMOR INVOICE</span><strong>{{ $invoiceNumber }}</strong><small>Terbit {{ $issuedAt->locale('id')->translatedFormat('d F Y') }}</small></td>
    </tr>
  </table>

  <table class="title-row">
    <tr><td><h1>Invoice Security Deposit</h1><p class="subtitle">Tagihan deposit pekerjaan berdasarkan surat izin yang telah ditinjau.</p></td><td style="text-align:right"><span class="status">MENUNGGU PEMBAYARAN</span></td></tr>
  </table>

  <table class="summary">
    <tr>
      <td class="amount-cell"><span>JUMLAH YANG HARUS DIBAYAR</span><strong>Rp {{ number_format((float) $permit->deposit_amount, 0, ',', '.') }}</strong></td>
      <td class="due-cell"><span>JATUH TEMPO</span><strong>{{ \Carbon\Carbon::parse($dueDate)->locale('id')->translatedFormat('d F Y') }}</strong></td>
    </tr>
  </table>

  @php
    $resolvedRecipientName = $recipientName ?? $permit->resolvedDepositInvoiceRecipientName();
    $resolvedRecipientAddress = $recipientAddress ?? $permit->deposit_invoice_recipient_address;
  @endphp

  <table class="two-column">
    <tr>
      <td>
        <p class="section-title">Ditagihkan kepada</p>
        <div class="panel"><table class="data">
          <tr><td class="label">Nama penerima</td><td class="value">{{ $resolvedRecipientName }}</td></tr>
          @if(filled($resolvedRecipientAddress))
            <tr><td class="label">Alamat penagihan</td><td class="value value-address">{!! nl2br(e($resolvedRecipientAddress)) !!}</td></tr>
          @endif
          <tr><td class="label">Referensi izin</td><td class="value">{{ $permit->permit_number }}</td></tr>
        </table></div>
      </td>
      <td>
        <p class="section-title">Rincian pekerjaan</p>
        <div class="panel"><table class="data">
          <tr><td class="label">Pekerjaan</td><td class="value">{{ $permit->work_type }}</td></tr>
          <tr><td class="label">Lokasi</td><td class="value">{{ $permit->work_location }}</td></tr>
          <tr><td class="label">Periode</td><td class="value">{{ $permit->start_date->locale('id')->translatedFormat('d M Y') }} sampai {{ $permit->end_date->locale('id')->translatedFormat('d M Y') }}</td></tr>
        </table></div>
      </td>
    </tr>
  </table>

  <p class="section-title">Rekening pembayaran</p>
  <table class="accounts">
    <thead><tr><th>Bank</th><th>Nomor rekening</th><th>Nama pemilik rekening</th></tr></thead>
    <tbody>
      @foreach($accounts as $account)
        <tr><td><strong>{{ $account->bank_name }}</strong></td><td><strong>{{ $account->account_number }}</strong></td><td><strong>{{ $account->account_holder }}</strong></td></tr>
      @endforeach
    </tbody>
  </table>

  @if($setting->payment_instructions)
    <div class="instruction"><strong>Instruksi pembayaran</strong><p>{{ $setting->payment_instructions }}</p></div>
  @endif

  <table class="authorization">
    <tr>
      <td class="authorization-copy">
        <strong>Pengesahan penerbit</strong>
        <p>Invoice ini diterbitkan oleh sistem perizinan Mal Bali Galeria. Cantumkan nomor invoice pada keterangan pembayaran. Finance akan memeriksa dana sebelum surat izin diterbitkan.</p>
        @if($signatureMethod === 'barcode')<p>Pindai QR untuk memeriksa identitas penerbit dan keutuhan file invoice.</p>@endif
      </td>
      <td class="signature-cell">
        <span class="signature-label">Diterbitkan oleh</span>
        <img class="signature-image {{ $signatureMethod === 'barcode' ? 'qr' : '' }}" src="{{ $signatureDataUri }}" alt="">
        <span class="issuer-name">{{ $issuerName }}</span>
        <span class="issuer-title">{{ $issuerTitle }}</span>
        @if($signatureMethod === 'barcode')<span class="document-id">ID {{ $documentId }}</span>@endif
      </td>
    </tr>
  </table>

  <table class="footer"><tr><td>{{ $invoiceNumber }}</td><td>Dokumen dibuat secara elektronik pada {{ $issuedAt->format('d-m-Y H:i') }} WITA</td></tr></table>
</body>
</html>
