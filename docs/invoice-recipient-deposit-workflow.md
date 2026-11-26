# Rencana Tujuan Penagihan Invoice Security Deposit

## Ringkasan

Tambahkan data tujuan penagihan pada proses penentuan security deposit izin kerja. Validator MEP dapat mengisi **Nama penerima / perusahaan** dan **Alamat penagihan**. Kedua field bersifat opsional pada pemeriksaan pertama.

Jika validator tidak mengisi data tujuan invoice:

- Nama invoice memakai gabungan nama tenant dan kontraktor atau vendor.
- Alamat tetap kosong dan baris alamat tidak dicetak pada invoice.

Jika permohonan memerlukan koordinasi, validator memeriksa data tersebut kembali saat menekan **Koordinasi selesai**. Setelah validator mengonfirmasi data tujuan invoice, MEP dan Finance tidak dapat mengubahnya.

## Perubahan Data dan Aturan Bisnis

Tambahkan metadata nullable berikut pada `work_permits`:

- `deposit_invoice_recipient_name`
- `deposit_invoice_recipient_address`
- `deposit_invoice_recipient_source`, dengan nilai `application` atau `custom`
- `deposit_invoice_recipient_confirmed_by`
- `deposit_invoice_recipient_confirmed_at`

Aturan penyimpanan:

- Nama maksimal 200 karakter.
- Alamat maksimal 500 karakter dan dapat menggunakan beberapa baris.
- Sistem memangkas spasi serta menormalkan line break sebelum menyimpan data.
- Jika nama dan alamat kosong, sistem memakai sumber `application`.
- Nama default menggabungkan nama tenant dan kontraktor atau vendor seperti invoice saat ini.
- Alamat default tetap kosong karena form permohonan tidak memiliki alamat penagihan.
- Data tanpa `deposit_invoice_recipient_confirmed_at` dianggap sebagai draft.
- Server menolak perubahan data setelah konfirmasi.
- Sistem tidak mengubah invoice yang sudah diterbitkan.

## Pemeriksaan Awal MEP

Ketika validator memilih **Security Deposit diperlukan**, tampilkan bagian **Tujuan penagihan invoice**:

- **Nama penerima / perusahaan**, opsional.
- **Alamat penagihan**, opsional.
- Keterangan bahwa field kosong akan memakai data tenant dan kontraktor dari permohonan.

Perilaku form:

- Tanpa koordinasi, sistem langsung mengonfirmasi data sebelum status berpindah ke `awaiting_invoice`.
- Dengan koordinasi, sistem menyimpan data sebagai draft.
- Jika validator hanya mengisi salah satu field, field lainnya memakai fallback masing-masing.
- Jika deposit tidak diperlukan, sistem menyembunyikan dan menonaktifkan kedua field serta tidak menyimpan nilainya.
- Jika validator juga meminta revisi data pemohon, draft tetap tersimpan tetapi belum dikunci.

Endpoint keputusan deposit menerima input baru:

- `deposit_invoice_recipient_name`: nullable, string, maksimal 200 karakter.
- `deposit_invoice_recipient_address`: nullable, string, maksimal 500 karakter.

## Penyelesaian Koordinasi

Untuk izin yang memerlukan deposit, tombol **Koordinasi selesai** membuka dialog konfirmasi sebelum form dikirim.

Dialog menyediakan dua pilihan:

1. **Gunakan data permohonan**
   - Tampilkan preview gabungan tenant dan kontraktor atau vendor.
   - Jelaskan bahwa alamat penagihan tidak tersedia dari form permohonan.
2. **Gunakan data lain**
   - Tampilkan field Nama penerima / perusahaan.
   - Tampilkan field Alamat penagihan.

Di bawah pilihan, tampilkan preview **Invoice akan ditagihkan kepada**.

Jika validator sudah mengisi data pada pemeriksaan pertama:

- Dialog memilih **Gunakan data lain** secara otomatis.
- Field menampilkan data sebelumnya.
- Validator masih dapat mengubah data sebelum konfirmasi.

Jika validator belum mengisi data:

- Dialog memilih **Gunakan data permohonan** secara otomatis.
- Validator dapat beralih ke **Gunakan data lain**.

Aturan validasi dialog:

- Jika validator memilih data lain, minimal salah satu field harus diisi.
- Kesalahan validasi tidak menyelesaikan koordinasi.
- Setelah redirect validasi, halaman membuka kembali dialog dan mempertahankan input.
- Server mengabaikan manipulasi input yang mencoba melewati aturan status.

Jika masih ada revisi pemohon lain, sistem menyimpan data sebagai draft dan belum menguncinya. Sistem baru mengonfirmasi data ketika alur siap menuju proses invoice.

Dialog menggunakan elemen native dan harus mendukung:

- Navigasi keyboard.
- Tombol Escape untuk menutup dialog.
- Focus trap dan pengembalian fokus ke tombol pembuka.
- Target sentuh minimal 44px.
- Form fallback yang tetap dapat dikirim tanpa JavaScript.
- Tampilan tanpa horizontal overflow pada layar ponsel.

Endpoint penyelesaian koordinasi menerima input baru:

- `deposit_invoice_recipient_mode`: wajib untuk alur deposit, dengan nilai `application` atau `custom`.
- `deposit_invoice_recipient_name`: nullable, string, maksimal 200 karakter.
- `deposit_invoice_recipient_address`: nullable, string, maksimal 500 karakter.

## Penerbitan Invoice

`DepositInvoiceService` harus memastikan data tujuan penagihan sudah dikonfirmasi sebelum menerbitkan invoice.

Invoice otomatis menggunakan snapshot final:

- Bagian **Ditagihkan kepada** menampilkan nama penerima final.
- Baris alamat hanya dicetak jika alamat tersedia.
- Penanggung jawab dan referensi izin tetap tampil sebagai data terpisah.

Untuk mode invoice manual:

- Halaman Finance menampilkan kartu tujuan penagihan final di atas form unggah invoice.
- Finance hanya dapat membaca data tersebut.
- Sistem tidak menyisipkan data ke PDF yang diunggah. Finance harus menyiapkan invoice manual sesuai snapshot yang ditampilkan.

Aturan penerbitan:

- Mode otomatis menghasilkan invoice dengan snapshot yang telah dikonfirmasi.
- Mode manual mengirim permohonan ke antrean Finance dengan snapshot yang sama.
- Kegagalan generator otomatis tidak membuka kembali perubahan data. Fallback manual tetap memakai snapshot final.
- Permohonan lama tanpa metadata tujuan invoice memakai gabungan tenant dan kontraktor atau vendor serta alamat kosong.
- Klik ganda dan request bersamaan tidak boleh membuat dua invoice.
- Sistem menjalankan perubahan status, konfirmasi tujuan invoice, dan penerbitan metadata dalam transaksi dengan row lock.

## Audit dan Keamanan

- Catat aktor, waktu konfirmasi, sumber data, dan informasi apakah alamat tersedia.
- Jangan menyalin alamat lengkap ke status log atau audit log.
- Simpan alamat hanya pada data izin kerja yang memiliki otorisasi sesuai alur saat ini.
- Tolak perubahan tujuan invoice setelah data dikonfirmasi, termasuk melalui request langsung.
- Pertahankan pemeriksaan hash, URL sementara, dan otorisasi unduhan invoice yang sudah ada.
- Invoice lama dan invoice yang sudah terbit tidak ikut diperbarui.

## Pengujian dan Kriteria Penerimaan

- Deposit tidak diperlukan tidak menampilkan atau menyimpan tujuan invoice.
- Deposit tanpa koordinasi dan field kosong memakai gabungan tenant dan kontraktor atau vendor serta tanpa alamat.
- Data khusus tanpa koordinasi langsung muncul pada invoice otomatis.
- Deposit dengan koordinasi menyimpan tujuan invoice sebagai draft.
- Dialog koordinasi menampilkan data permohonan ketika draft kosong.
- Draft yang sudah diisi tampil kembali dan dapat diperbarui sebelum konfirmasi.
- Pilihan data lain tanpa nama maupun alamat ditolak.
- Kesalahan validasi tidak menyelesaikan koordinasi dan membuka kembali dialog.
- Revisi lain yang masih aktif tidak mengunci draft atau menerbitkan invoice.
- Setelah konfirmasi, MEP dan Finance tidak dapat mengubah tujuan invoice.
- Mode otomatis menggunakan snapshot final pada PDF.
- Mode manual menampilkan snapshot kepada Finance tanpa mengubah PDF unggahan.
- Permohonan lama tanpa metadata tujuan invoice tetap dapat menghasilkan invoice menggunakan fallback.
- Kegagalan generator mempertahankan snapshot untuk unggahan manual.
- Dua request penyelesaian koordinasi tidak menghasilkan invoice ganda.
- Audit log tidak menyimpan alamat lengkap.
- Jalankan test alur MEP, koordinasi, invoice otomatis dan manual, backlog, PDF, audit, serta otorisasi.
- Jalankan kompilasi Blade dan build aset.
- Jangan menjalankan browser otomatis. Pengguna melakukan pemeriksaan manual pada lebar 375px, 768px, dan desktop.

## Asumsi

- Fitur hanya berlaku pada izin kerja yang diperiksa MEP dan membutuhkan security deposit.
- Label field adalah **Nama penerima / perusahaan** dan **Alamat penagihan**.
- Nama default invoice menggabungkan tenant dan kontraktor atau vendor.
- Form permohonan awal tidak mendapat field alamat baru.
- PDF menghilangkan baris alamat jika alamat kosong.
- Finance hanya dapat membaca tujuan invoice final.
- Data menjadi final saat alur siap masuk ke proses invoice, bukan hanya saat draft pertama disimpan.
- Antislop diterapkan pada copy, dialog, aksesibilitas, dan tampilan mobile selama implementasi.
