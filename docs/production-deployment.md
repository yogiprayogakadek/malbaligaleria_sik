# Deployment Production

## Konfigurasi wajib

Pastikan nilai berikut tersedia di `.env` production. Jangan menyimpan secret asli di repository.

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sik.malbaligaleria.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
REVERB_ALLOWED_ORIGINS=https://sik.malbaligaleria.com
```

Backup terenkripsi dan pemindaian malware menghasilkan peringatan bila belum diaktifkan. Keduanya sebaiknya aktif sebelum sistem menerima data pemohon sebenarnya.

## Preflight manual

Sebelum migration, izinkan preflight melaporkan migration tertunda sebagai peringatan:

```bash
php artisan app:preflight --before-migrate
```

Setelah migration, gunakan mode ketat. Command keluar dengan kode gagal bila masih ada migration tertunda atau konfigurasi penting tidak aman.

```bash
php artisan app:preflight
```

## Script deployment

Script tidak menjalankan `git pull` agar perubahan sumber tetap diperiksa oleh operator. Setelah mengambil commit yang diinginkan, jalankan:

```bash
cd /var/www/sik
git pull --ff-only origin main
chmod +x scripts/deploy-production.sh
APP_DIR=/var/www/sik scripts/deploy-production.sh
```

Script memasang dependency production, membangun aset, menjalankan preflight awal, mengaktifkan maintenance mode, menjalankan migration, membuat cache aplikasi, menjalankan preflight ketat, me-restart queue worker, lalu membuka aplikasi kembali. Bila salah satu tahap gagal setelah maintenance mode aktif, trap akan membuka aplikasi kembali agar tidak tertinggal dalam mode maintenance.

Gunakan `SKIP_ASSET_BUILD=1` hanya bila aset sudah dibangun oleh pipeline terpisah:

```bash
SKIP_ASSET_BUILD=1 APP_DIR=/var/www/sik scripts/deploy-production.sh
```

Supervisor atau systemd tetap harus menjaga proses berikut berjalan:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120
php artisan schedule:work
php artisan reverb:start --host=127.0.0.1 --port=8080
```

Setiap halaman error menampilkan request ID. Gunakan nilai tersebut untuk mencari request terkait pada `storage/logs/laravel.log` tanpa meminta pengguna mengirim detail teknis atau screenshot stack trace.
