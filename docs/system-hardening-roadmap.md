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
- [x] SLA per jenis permohonan, penanda antrean terlambat, dan eskalasi otomatis.
- [x] Penguncian transaksi pemeriksaan agar keputusan validator tidak diproses bersamaan.
- [x] Penugasan permohonan kepada validator tertentu beserta mekanisme ambil alih.
- [x] Timeline komunikasi yang menggabungkan status, revisi, email, WhatsApp, dan push.
- [x] Dashboard laporan dan ekspor Excel atau PDF.
- [x] Pemindaian malware ClamAV untuk seluruh dokumen yang diunggah, dengan mode gagal tertutup saat diaktifkan.
- [x] Kebijakan retensi dan penghapusan otomatis KTP, SIM, surat tugas, serta bukti finansial.
- [x] Watermark identitas staf pada gambar dan setiap halaman PDF sensitif, disertai audit akses.
- [x] Penyimpanan draft otomatis terenkripsi untuk formulir panjang tanpa menyimpan lampiran.
- [x] Permintaan revisi per field dengan alasan yang terstruktur.
- [x] Pencarian global nomor surat, tenant, PIC, telepon, kategori, dan status.
- [x] Pencegahan permohonan ganda dengan token idempotensi per formulir dan indeks unik database.
- [x] Tautan status WhatsApp dan pengecekan status tetap tersedia di luar jam pengajuan.
- [x] Halaman error aman dan konsisten untuk 403, 404, 419, 429, 500, dan gangguan layanan 503.
- [x] Request ID unik pada respons, log, dan halaman error untuk pelacakan insiden.
- [x] Sequence database atomic untuk mencegah benturan nomor surat loading.
- [x] Preflight dan script deployment production dengan pemeriksaan migration serta konfigurasi keamanan.

## Penguatan lanjutan

- [x] Header keamanan browser terpusat tanpa memblokir kamera dan geolokasi scanner.
- [x] Pembatasan trusted host production untuk mencegah manipulasi header Host.
- [ ] Content Security Policy berbasis nonce setelah seluruh inline script dipindahkan ke asset terkompilasi.
- [ ] Pusat failed queue jobs dengan detail aman, retry terkontrol, dan audit admin.
- [ ] Peringatan keamanan untuk percobaan login staf berulang dan login dari perangkat baru.
- [ ] Prosedur rotasi kredensial APP_KEY, Reverb, SMTP, WAHA, VAPID, dan kunci backup.

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

Setelah setiap deployment yang membawa migration baru, jalankan sebelum worker dimulai ulang:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
```

Backup terenkripsi memerlukan kunci khusus dan lokasi di luar direktori aplikasi. Panduan lengkap tersedia di `docs/backup-recovery.md`.
Prosedur deployment dan preflight tersedia di `docs/production-deployment.md`.
