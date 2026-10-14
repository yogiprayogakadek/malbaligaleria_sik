# Roadmap Penguatan Sistem Perizinan

Dokumen ini mencatat fitur operasional dan keamanan yang sudah tersedia, selesai pada batch penguatan, dan masih perlu dikerjakan.

## Sudah tersedia sebelumnya

- [x] Tautan status dan dokumen sensitif menggunakan token acak atau URL bertanda tangan.
- [x] Masa berlaku tautan status pemohon.
- [x] Hak akses terpisah untuk admin, TR, MEP, Finance, Secretary, dan tenant.
- [x] Akun nonaktif langsung kehilangan akses pada request berikutnya.
- [x] Akun hasil seeder wajib mengganti kata sandi saat login pertama.
- [x] Notifikasi push, email, dan WhatsApp untuk alur yang didukung.
- [x] Log pengiriman WhatsApp beserta status gagal atau terkirim.
- [x] Riwayat status surat izin kerja.
- [x] Jam operasional mingguan dan halaman portal tutup.
- [x] Pembatasan lokasi scanner dan mode akses dari mana saja.
- [x] Validasi file, MIME, ukuran, dan otorisasi akses dokumen.

## Selesai pada batch ini

- [x] Audit log lintas modul untuk create, update, dan delete.
- [x] Audit mencatat pelaku, waktu, route, metode request, IP, perangkat, serta nilai sebelum dan sesudah.
- [x] Password, token, API key, dan path dokumen disamarkan dari audit.
- [x] Audit log bersifat append-only melalui model aplikasi.
- [x] Halaman audit admin dengan filter aktivitas dan modul.
- [x] Pusat kesehatan sistem untuk database, cache, storage, queue, email, WhatsApp, dan Reverb.
- [x] Queue heartbeat terjadwal untuk mendeteksi worker yang berhenti.
- [x] Origin Reverb dibatasi melalui `REVERB_ALLOWED_ORIGINS`.
- [x] Tanggal khusus untuk hari libur, maintenance, tutup penuh, buka 24 jam, atau jam khusus.
- [x] Navigasi Sistem admin yang lebih ringkas pada mobile.

## Prioritas berikutnya

- [x] 2FA TOTP wajib untuk seluruh akun staf dengan recovery code sekali pakai.
- [x] Daftar sesi aktif dan tombol mencabut sesi perangkat lain dengan konfirmasi kata sandi.
- [x] Notifikasi push otomatis kepada admin ketika pemeriksaan kesehatan menemukan gangguan baru.
- [x] Log pengiriman email terenkripsi dan tombol kirim ulang pesan gagal.
- [x] Backup database dan dokumen terenkripsi, terjadwal, diverifikasi setiap pembuatan, serta diuji proses restore-nya setiap minggu.
- [ ] SLA per jenis permohonan, penanda antrean terlambat, dan eskalasi otomatis.
- [x] Penguncian transaksi pemeriksaan agar keputusan validator tidak diproses bersamaan.
- [ ] Penugasan permohonan kepada validator tertentu beserta mekanisme ambil alih.
- [ ] Timeline komunikasi yang menggabungkan status, revisi, email, WhatsApp, dan push.
- [ ] Dashboard laporan dan ekspor Excel atau PDF.
- [ ] Pemindaian malware untuk seluruh dokumen yang diunggah.
- [ ] Kebijakan retensi dan penghapusan otomatis KTP, SIM, serta bukti pembayaran.
- [ ] Watermark identitas pengguna saat staf membuka dokumen sensitif.
- [ ] Penyimpanan draft otomatis untuk formulir panjang.
- [ ] Permintaan revisi per field dengan alasan yang terstruktur.
- [ ] Pencarian global nomor surat, tenant, PIC, telepon, kategori, dan status.

## Kebutuhan operasional baru

Queue heartbeat memerlukan scheduler dan worker berjalan bersamaan:

```bash
php artisan schedule:work
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

Untuk production, jalankan keduanya melalui Supervisor atau systemd. Atur origin Reverb sesuai domain publik:

```env
REVERB_ALLOWED_ORIGINS=https://sik.malbaligaleria.com
```

Backup terenkripsi memerlukan kunci khusus dan lokasi di luar direktori aplikasi. Panduan lengkap tersedia di `docs/backup-recovery.md`.
