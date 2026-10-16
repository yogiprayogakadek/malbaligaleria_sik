# Rotasi Kredensial Production

Panduan ini digunakan untuk mengganti kredensial tanpa memutus layanan atau kehilangan akses ke data terenkripsi. Kerjakan satu jenis kredensial dalam satu maintenance window, siapkan rollback, dan jangan menaruh nilai rahasia di Git, tiket, chat, atau log terminal bersama.

## Persiapan wajib

1. Pastikan backup terenkripsi terbaru lolos `php artisan backup:test-restore`.
2. Simpan salinan `.env` lama di penyimpanan rahasia dengan akses terbatas.
3. Jalankan `php artisan app:preflight` dan pastikan tidak ada pemeriksaan kritis yang gagal.
4. Catat waktu, pelaksana, jenis kredensial, dan hasil verifikasi. Jangan catat nilai rahasianya.
5. Setelah `.env` berubah, selalu jalankan:

```bash
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
sudo supervisorctl restart sik-reverb
```

Sesuaikan nama proses Supervisor atau systemd dengan konfigurasi server.

## APP_KEY

`APP_KEY` melindungi secret 2FA, recovery code, password SMTP, API key WAHA, alamat penerima log, dan audit log. Jangan menggantinya dengan `php artisan key:generate` secara langsung pada production.

1. Catat nilai `APP_KEY` lama sebagai `OLD_KEY` di password manager.
2. Buat kunci baru tanpa mengubah aplikasi:

```bash
php artisan key:generate --show
```

3. Atur kunci baru sebagai `APP_KEY` dan masukkan kunci lama ke `APP_PREVIOUS_KEYS`:

```env
APP_KEY=base64:KUNCI_BARU
APP_PREVIOUS_KEYS=base64:KUNCI_LAMA
```

4. Bersihkan cache konfigurasi dan mulai ulang worker.
5. Uji login 2FA, halaman konfigurasi email/WhatsApp, audit log, dan pengiriman notifikasi.
6. Pertahankan kunci lama di `APP_PREVIOUS_KEYS` sampai seluruh data terenkripsi sudah ditulis ulang dengan kunci baru dan satu siklus backup-retensi selesai.

Rollback: kembalikan `APP_KEY` lama, hapus perubahan `APP_PREVIOUS_KEYS`, lalu bersihkan cache dan mulai ulang worker.

## Reverb

1. Buat `REVERB_APP_KEY` dan `REVERB_APP_SECRET` acak dengan minimal 32 byte.
2. Perbarui nilai server dan variabel `VITE_REVERB_APP_KEY`.
3. Jalankan `npm ci && npm run build` karena app key digunakan oleh bundle frontend.
4. Bersihkan cache, mulai ulang Reverb dan worker, lalu uji notifikasi realtime dari tenant ke staf.
5. Cabut kredensial lama setelah seluruh klien memakai build baru.

## SMTP

1. Buat password mailbox baru di panel penyedia email.
2. Perbarui melalui menu **Konfigurasi Email** admin atau secret manager server.
3. Gunakan tombol kirim email uji.
4. Uji satu notifikasi permohonan melalui queue.
5. Cabut password lama setelah pengiriman berhasil dan log tidak menunjukkan kegagalan autentikasi.

## WAHA

1. Buat API key WAHA baru dalam bentuk plaintext. Jangan memasukkan hash API key ke aplikasi.
2. Perbarui WAHA lebih dahulu agar masa transisi menerima kunci baru sesuai kemampuan deployment WAHA.
3. Perbarui melalui menu **Konfigurasi WhatsApp** admin.
4. Kirim pesan uji dan periksa log pengiriman.
5. Cabut key lama setelah worker tidak lagi memproses job dengan konfigurasi lama.

## VAPID Web Push

Mengganti VAPID membuat subscription browser lama tidak dapat digunakan.

1. Buat pasangan public/private key baru di lingkungan aman.
2. Perbarui `VAPID_PUBLIC_KEY` dan `VAPID_PRIVATE_KEY` bersamaan.
3. Bersihkan cache dan mulai ulang worker.
4. Hapus subscription lama dari tabel `push_subscriptions` agar perangkat diminta berlangganan kembali.
5. Uji push pada desktop dan PWA mobile.

## Kunci backup

1. Jangan menimpa `BACKUP_ENCRYPTION_KEY` sebelum backup lama melewati masa retensi atau sudah dienkripsi ulang.
2. Simpan kunci lama dan baru dengan label periode yang jelas di password manager.
3. Atur kunci baru, buat backup baru, lalu jalankan `php artisan backup:test-restore`.
4. Pastikan prosedur restore memilih kunci yang sesuai dengan tanggal backup.

## Pemeriksaan akhir

- Login password dan 2FA berhasil.
- Email, WhatsApp, push, dan realtime Reverb berhasil.
- Queue, scheduler, serta health check berstatus sehat.
- Backup baru berhasil dibuat dan diuji restore.
- Kredensial lama sudah dicabut atau memiliki tanggal pencabutan yang jelas.
- Tidak ada nilai rahasia masuk ke Git atau log aplikasi.
