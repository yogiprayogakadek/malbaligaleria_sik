# Uji beban aplikasi

Jalankan pengujian hanya pada staging yang memakai salinan konfigurasi tanpa data pribadi. Script memblokir `si.malbaligaleria.com` dan `sik.malbaligaleria.com`. Target remote lain memerlukan `K6_REMOTE_CONFIRM=staging-only`.

Install k6 pada mesin penguji. Jangan menjalankan generator beban pada server aplikasi karena hasil CPU dan memori tidak lagi mewakili trafik pengguna.

## Persiapan staging

- Jalankan queue worker, scheduler, Reverb, database, dan document converter seperti production.
- Arahkan email ke Mailpit atau inbox uji.
- Gunakan sesi WAHA khusus pengujian dan nomor QA yang Anda kendalikan.
- Naikkan batas pengajuan melalui menu admin sesuai jumlah request pengujian.
- Pastikan portal sedang dalam jam operasional.
- Kosongkan data hasil uji setelah pengukuran selesai.

## Pengajuan dan upload kamera

Script membuka form untuk memperoleh cookie sesi, CSRF token, dan token pengajuan. Setiap iterasi mengirim permohonan loading baru dengan multipart foto sekitar 200 KB. Alur ini juga menjalankan penyimpanan file, audit, notifikasi staf, email pemohon, Web Push, dan antrean WhatsApp.

PowerShell lokal:

```powershell
$env:BASE_URL="http://127.0.0.1:8000"
$env:LOAD_TEST_EMAIL="loadtest@example.test"
$env:LOAD_TEST_PHONE="nomor-qa-anda"
$env:K6_VUS="3"
k6 run tests/load/permit-submission.js
```

Staging remote:

```bash
BASE_URL=https://staging.example.test \
LOAD_TEST_EMAIL=loadtest@example.test \
LOAD_TEST_PHONE=nomor-qa-anda \
K6_REMOTE_CONFIRM=staging-only \
K6_VUS=5 \
k6 run tests/load/permit-submission.js
```

Nilai bawaan menaikkan beban selama 20 detik, menahan beban selama 40 detik, lalu menurunkannya selama 10 detik. Ubah `K6_RAMP_UP`, `K6_HOLD`, dan `K6_RAMP_DOWN` bila perlu.

## Pembuatan dan download PDF

Salin file contoh di luar folder repository, isi dengan URL download surat staging yang sudah disetujui, lalu batasi akses file tersebut karena URL dapat memuat token pemohon.

```bash
cp tests/load/pdf-urls.example.txt /tmp/sik-pdf-urls.txt
chmod 600 /tmp/sik-pdf-urls.txt
PDF_URLS_PATH=/tmp/sik-pdf-urls.txt \
K6_REMOTE_CONFIRM=staging-only \
K6_VUS=3 \
k6 run tests/load/pdf-download.js
rm /tmp/sik-pdf-urls.txt
```

Gunakan surat yang belum pernah diunduh untuk mengukur generate dan penyimpanan snapshot PDF. Jalankan ulang URL yang sama untuk mengukur verifikasi hash serta download snapshot.

## Membaca hasil

Pengujian gagal bila lebih dari 1 persen request gagal, kurang dari 99 persen pemeriksaan lulus, p95 submit melewati 8 detik, atau p95 PDF melewati 5 detik. Angka ini adalah batas awal untuk staging, bukan klaim kapasitas production.

Bandingkan hasil k6 dengan menu Admin > Timeline Proses. Halaman tersebut menunjukkan durasi validasi, pemeriksaan file, query database, notifikasi, dan proses lain per pengajuan. Periksa juga jumlah job gagal dan kesehatan queue setelah k6 berhenti.
