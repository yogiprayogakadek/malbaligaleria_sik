# Progres Barang Keluar pada Scanner Security

## Perilaku

Surat loading dengan arah `out` atau `both` mencatat jumlah barang secara bertahap pada setiap `check_out`. Surat tetap dapat dipakai selama belum kedaluwarsa, belum dicabut, dan jumlah keluar belum mencapai `item_count`.

Setiap aktivitas gerbang menyimpan foto, petugas, waktu, dan catatan security opsional untuk TR. `check_in` tidak mengubah progres barang keluar.

## Invariant

- Total `item_quantity` dari event `check_out` tidak boleh melebihi `loading_permits.item_count`.
- Quantity hanya boleh diisi pada `check_out` untuk surat `out` atau `both`.
- Check-out lama dengan quantity `null` dianggap telah menyelesaikan surat.
- Surat selesai tidak menerbitkan gate challenge dan tidak menerima event baru.
- Event gerbang tidak dapat diubah atau dihapus.
- Foto hanya dapat dibuka melalui URL sementara oleh sesi security aktif, TR, atau Admin sesuai route masing-masing.

## Alur Security

1. Petugas memindai QR dan melihat progres serta carousel riwayat.
2. Jika surat masih aktif, petugas memilih `Tambahkan pemindaian` dan mengambil foto.
3. Saat aktivitas berikutnya adalah `check_out`, petugas mengisi quantity maksimal sebesar sisa barang.
4. Petugas dapat menambahkan catatan untuk TR pada setiap aktivitas.
5. Setelah total terpenuhi, scanner hanya menampilkan detail dan riwayat foto untuk surat tersebut.

## Kompatibilitas

Tidak ada status penyelesaian baru pada `loading_permits`. Status selesai selalu diturunkan dari event gerbang sehingga tidak ada dua sumber kebenaran. Surat arah `in` mempertahankan perilaku check-in/check-out sebelumnya.
