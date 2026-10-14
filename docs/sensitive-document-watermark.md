# Watermark Dokumen Sensitif

Dokumen sensitif yang dibuka staf tidak dikirim langsung dari storage. Aplikasi membuat salinan respons dengan watermark yang berisi nama staf, peran atau divisi, ID akun, serta waktu akses WITA.

Perlindungan berlaku untuk:

- KTP atau SIM permohonan loading dan surat izin kerja.
- Surat tugas.
- Bukti pembayaran security deposit.
- Bukti pengembalian security deposit.

Watermark dibakar ke piksel gambar dan ditanam pada setiap halaman PDF. File asli tetap tersimpan tanpa perubahan. Jika tipe file, struktur PDF, ukuran gambar, atau proses watermark tidak dapat diverifikasi, aplikasi menolak menampilkan dokumen dan tidak mengirim file asli sebagai fallback.

Setiap akses yang berhasil dicatat sebagai aktivitas `Dilihat` pada Audit Log. Catatan menyimpan akun staf, jenis dokumen, record permohonan, waktu, rute, dan metadata request tanpa menyimpan path file.

Server produksi memerlukan ekstensi PHP `gd`, `fileinfo`, dan `zlib`. Periksa dengan:

```bash
php -m | grep -E 'gd|fileinfo|zlib'
```
