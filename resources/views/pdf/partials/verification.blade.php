<table class="verification">
  <tr>
    <td><div class="qr-mark"><img class="qr-image" src="{{ $qrDataUri }}" alt="QR verifikasi"><span class="qr-logo"><img src="{{ $logoDataUri }}" alt=""></span></div><div class="qr-copy">Pindai untuk memverifikasi dokumen. ID dokumen: {{ $permit->official_document_id }}</div></td>
    <td class="signature"><div class="signature-date">Kuta, {{ ($permit->reviewed_at ?? now())->copy()->locale('id')->translatedFormat('d F Y') }}</div><div class="signature-line">Divisi {{ $divisionLabel }}</div><div class="signature-role">Mal Bali Galeria</div></td>
  </tr>
</table>
