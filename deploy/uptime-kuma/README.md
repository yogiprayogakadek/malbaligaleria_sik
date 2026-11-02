# Monitoring eksternal dengan Uptime Kuma

Jalankan Uptime Kuma di CT atau host yang berbeda dari server aplikasi. Dengan begitu, gangguan pada server web masih dapat dilaporkan oleh monitor.

## Menjalankan layanan

Docker dan plugin Compose harus sudah tersedia pada CT monitoring. Salin folder ini ke server, lalu jalankan:

```bash
cp .env.example .env
docker compose pull
docker compose up -d
docker compose ps
```

Data disimpan pada volume lokal `uptime-kuma-data`. Jangan pindahkan volume SQLite ini ke NFS. Konfigurasi bawaan hanya membuka port pada `127.0.0.1`; gunakan reverse proxy HTTPS dari `nginx.conf.example`, atau ubah bind address ke IP LAN bila akses memang dibatasi firewall internal.

Setelah halaman awal terbuka, buat akun administrator dengan kata sandi unik. Jangan membuat status page publik yang menampilkan nama komponen internal.

## Token aplikasi

Buat token khusus pada server aplikasi:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Simpan hasilnya sebagai `MONITORING_HEALTH_TOKEN` di `.env` Laravel, kemudian jalankan:

```bash
php artisan config:cache
```

Token wajib dikirim melalui header. Jangan menaruh token pada URL, nama monitor, catatan, atau screenshot.

## Monitor yang wajib dibuat

### 1. Aplikasi web

- Jenis: HTTP(s)
- URL: `https://si.malbaligaleria.com/up`
- Interval: 60 detik
- Retry: 2 kali
- Status yang diterima: 200
- Aktifkan peringatan masa berlaku sertifikat TLS

Endpoint `/up` tidak mengikuti jam operasional portal, sehingga penutupan pengajuan terjadwal tidak dianggap sebagai gangguan.

### 2. Proses operasional

- Jenis: HTTP(s)
- URL: `https://si.malbaligaleria.com/health/operations`
- Interval: 60 detik
- Retry: 2 kali
- Status yang diterima: 200
- Header: `Authorization: Bearer <MONITORING_HEALTH_TOKEN>`

Respons 503 berarti heartbeat scheduler, queue worker, atau pemeriksaan SLA terlambat. Respons 404 biasanya berarti token tidak ada atau salah; aplikasi sengaja tidak mengungkap keberadaan endpoint kepada request tanpa token yang valid.

Hubungkan kedua monitor ke minimal satu kanal notifikasi yang tidak bergantung pada server aplikasi ini. Lakukan uji dengan menghentikan monitor hanya pada waktu pemeliharaan yang terjadwal, lalu pastikan notifikasi gangguan dan pemulihan diterima.

## Pemeliharaan

```bash
docker compose pull
docker compose up -d
docker compose logs --tail=100 uptime-kuma
```

Backup volume `uptime-kuma-data` secara berkala ketika container dihentikan agar salinan SQLite konsisten.
