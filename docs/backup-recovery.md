# Backup dan Pemulihan

Backup SIK berisi snapshot database dan seluruh dokumen pada storage privat. Arsip dienkripsi per blok menggunakan AES-256-GCM dan selalu diverifikasi setelah dibuat.

## Konfigurasi server

Buat kunci khusus backup. Jangan menggunakan `APP_KEY` dan simpan salinan kunci di password manager atau lokasi lain di luar VPS.

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Tambahkan hasilnya ke `.env` server:

```env
BACKUP_ENABLED=true
BACKUP_ENCRYPTION_KEY=base64:HASIL_PERINTAH_DI_ATAS
BACKUP_PATH=/var/backups/sik
BACKUP_RETENTION_DAYS=14
BACKUP_SCHEDULE=01:30
MYSQLDUMP_BINARY=/usr/bin/mysqldump
```

Buat direktori yang hanya dapat diakses akun service aplikasi:

```bash
sudo install -d -m 700 -o www-data -g www-data /var/backups/sik
```

Pastikan `php artisan schedule:work` atau cron Laravel aktif. Backup dibuat setiap hari dan uji restore tanpa perubahan data produksi dijalankan setiap Senin.

Salin file `.mbgbak` secara berkala ke NAS, object storage, atau server backup yang terpisah. Direktori pada VPS yang sama hanya melindungi dari kesalahan aplikasi, bukan kerusakan disk atau kehilangan server. Simpan kunci enkripsi di lokasi yang berbeda dari arsip.

## Pengujian manual

```bash
php artisan backup:create
php artisan backup:test-restore
```

Perintah kedua mendekripsi dan mengekstrak backup terbaru ke direktori sementara, memeriksa manifest serta snapshot database, lalu menghapus hasil uji. Status dan usia backup dapat dilihat pada menu **Kesehatan Sistem**.

## Pemulihan insiden

Jangan memulihkan langsung di atas server aktif. Siapkan server atau database kosong, salin file `.mbgbak` serta kuncinya, lalu jalankan:

```bash
php artisan backup:test-restore /path/file.mbgbak
php artisan backup:restore-stage /path/file.mbgbak
```

Perintah kedua mempertahankan hasil dekripsi pada `storage/app/restore-staging`. Setelah isi dan hak akses diperiksa, muat `database/database.sql` dengan klien MySQL dan pindahkan isi `documents/` ke `storage/app/private`. Aplikasi tidak menjalankan pemulihan destruktif secara otomatis.

Kunci enkripsi tidak disimpan di dalam backup. Kehilangan kunci berarti backup tidak dapat dipulihkan.
