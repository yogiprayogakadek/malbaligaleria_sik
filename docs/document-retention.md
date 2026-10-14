# Retensi Dokumen Sensitif

Kebijakan retensi menghapus file privat tanpa menghapus record permohonan, status, atau audit. Fitur awalnya nonaktif dan dapat diatur melalui menu Admin > Retensi Dokumen.

Dokumen yang dicakup:

- KTP atau SIM pada permohonan loading dan surat izin kerja.
- Surat tugas pekerjaan rooftop atau ATM.
- Bukti pembayaran dan bukti pengembalian security deposit.

Permohonan yang masih diperiksa, menunggu pembayaran, sedang dikerjakan, atau masih dalam proses refund tidak diproses. Bukti finansial baru masuk masa retensi setelah permohonan ditolak atau deposit selesai dikembalikan.

## Scheduler

Penghapusan dijadwalkan setiap hari pukul 02.30 waktu aplikasi. Pastikan cron Laravel aktif:

```cron
* * * * * cd /var/www/sik && php artisan schedule:run >> /dev/null 2>&1
```

Periksa jadwal yang terdaftar:

```bash
php artisan schedule:list
```

Hitung kandidat tanpa menghapus file:

```bash
php artisan permits:purge-sensitive-documents --dry-run
```

Proses manual hanya berjalan ketika kebijakan telah diaktifkan oleh admin:

```bash
php artisan permits:purge-sensitive-documents
```

Command mengembalikan status gagal jika ada path tidak sah atau storage tidak dapat menghapus file. Log hanya menyimpan jenis permohonan, ID record, dan nama field, bukan nama maupun path file.
