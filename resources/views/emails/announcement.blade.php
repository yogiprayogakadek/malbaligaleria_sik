<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $announcementTitle }}</title></head>
<body style="margin:0;background:#eef3f8;color:#17253a;font-family:Arial,sans-serif">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef3f8;padding:28px 12px"><tr><td align="center">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #dce4ee">
      <tr><td style="padding:22px 26px;border-bottom:3px solid #2466a8"><img src="cid:{{ \App\Support\MailBranding::LOGO_CONTENT_ID }}" width="96" alt="Mal Bali Galeria" style="display:block;max-width:96px;height:auto"></td></tr>
      <tr><td style="padding:28px 26px"><p style="margin:0 0 10px;color:#66758a;font-size:13px">Halo {{ $recipientName ?: 'Bapak/Ibu' }},</p><h1 style="margin:0 0 18px;color:#173e70;font-size:22px;line-height:1.35">{{ $announcementTitle }}</h1><div style="color:#33445a;font-size:14px;line-height:1.7;white-space:pre-line">{{ $announcementMessage }}</div></td></tr>
      <tr><td style="padding:16px 26px;border-top:1px solid #dce4ee;color:#718096;font-size:11px;line-height:1.5">Mal Bali Galeria · Property Management<br>Pesan ini dikirim melalui Sistem Informasi Perizinan.</td></tr>
    </table>
  </td></tr></table>
</body>
</html>
