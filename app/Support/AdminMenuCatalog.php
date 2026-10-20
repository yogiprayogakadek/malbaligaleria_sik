<?php

namespace App\Support;

class AdminMenuCatalog
{
    public static function groups(): array
    {
        return [
            [
                'label' => 'Perizinan',
                'items' => [
                    self::item('Pusat pengiriman', 'admin.communication-logs.index', 'i-mail', 'Pantau email, WhatsApp, push, retry, dan fallback pesan gagal.', ['admin.communication-logs.*']),
                    self::item('Ringkasan', 'admin.dashboard', 'i-grid', 'Gambaran antrean, status, dan aktivitas permohonan terbaru.', ['admin.dashboard']),
                    self::item('Pencarian perizinan', 'admin.search', 'i-search', 'Cari permohonan berdasarkan nomor surat, pemohon, atau tenant.', ['admin.search']),
                    self::item('Validasi darurat', 'admin.emergency-validation.index', 'i-shield-check', 'Ambil keputusan saat validator berhalangan dengan alasan dan audit khusus admin.', ['admin.emergency-validation.*']),
                    self::item('Data loading barang', 'admin.loading.index', 'i-truck', 'Pantau seluruh permohonan loading dan unloading tanpa melakukan validasi.', ['admin.loading.*'], 'pendingTRCount'),
                    self::item('Kategori izin kerja', 'admin.work-categories.index', 'i-wrench', 'Atur kategori, divisi pemeriksa, dan batas durasi Surat Izin Kerja.', ['admin.work-categories.*']),
                    self::item('Durasi izin kerja', 'admin.settings.work-permit-durations.edit', 'i-calendar', 'Atur masa berlaku untuk pekerjaan umum, renovasi, dan pembongkaran.', ['admin.settings.work-permit-durations.*']),
                    self::item('Master tenant', 'admin.tenants.index', 'i-building', 'Kelola tenant, unit toko, PIC, dan hubungan akun tenant.', ['admin.tenants.*']),
                    self::item('Laporan perizinan', 'admin.reports.index', 'i-file', 'Filter, rekap, dan ekspor data perizinan ke Excel atau PDF.', ['admin.reports.*']),
                    self::item('Akun validator', 'admin.validators.index', 'i-user', 'Kelola akun validator, secretary, Finance, dan divisi operasional.', ['admin.validators.*']),
                ],
            ],
            [
                'label' => 'Komunikasi',
                'items' => [
                    self::item('Pengumuman massal', 'admin.announcements.index', 'i-bell', 'Kirim pengumuman WhatsApp atau email secara bertahap kepada akun dan pemohon.', ['admin.announcements.*']),
                    self::item('Pengingat antrean', 'admin.queue-reminders.index', 'i-bell', 'Kirim ulang notifikasi antrean tertentu kepada validator yang bertugas.', ['admin.queue-reminders.*']),
                    self::item('Template pesan', 'admin.message-templates.index', 'i-edit', 'Kelola subjek dan isi notifikasi operasional.', ['admin.message-templates.*']),
                    self::item('Konfigurasi email', 'admin.settings.mail.edit', 'i-mail', 'Atur server SMTP dan uji pengiriman email sistem.', ['admin.settings.mail.*']),
                    self::item('Log email', 'admin.email-logs.index', 'i-file', 'Periksa status, kegagalan, dan percobaan ulang email.', ['admin.email-logs.*']),
                    self::item('Konfigurasi WhatsApp', 'admin.settings.whatsapp.edit', 'i-phone', 'Atur koneksi WAHA serta penerima notifikasi WhatsApp.', ['admin.settings.whatsapp.*']),
                ],
            ],
            [
                'label' => 'Sistem',
                'items' => [
                    self::item('Jam operasional', 'admin.schedule.edit', 'i-clock', 'Tentukan waktu layanan dan pengecualian jadwal operasional.', ['admin.schedule.*']),
                    self::item('Batas pengajuan', 'admin.settings.permit-limits.edit', 'i-clock', 'Batasi jumlah permohonan untuk mencegah penyalahgunaan.', ['admin.settings.permit-limits.*']),
                    self::item('Akses scanner', 'admin.settings.scanner.edit', 'i-scan', 'Atur akses scanner secara bebas atau berdasarkan radius lokasi.', ['admin.settings.scanner.*']),
                    self::item('Log pemindaian', 'admin.scanner-logs.index', 'i-file', 'Tinjau riwayat pemindaian QR dan alamat yang diblokir.', ['admin.scanner-logs.*']),
                    self::item('Keamanan sistem', 'admin.settings.security.edit', 'i-lock', 'Kelola 2FA staf, peringatan login, dan riwayat keamanan.', ['admin.settings.security.*']),
                    self::item('Retensi dokumen', 'admin.settings.retention.edit', 'i-trash', 'Atur masa simpan dan pembersihan dokumen sensitif.', ['admin.settings.retention.*']),
                    self::item('Pola nomor surat', 'admin.settings.permit-numbers.edit', 'i-edit', 'Atur format dan periode nomor urut surat baru.', ['admin.settings.permit-numbers.*']),
                    self::item('Manajemen data', 'admin.data-maintenance.index', 'i-box', 'Hapus data operasional tertentu melalui kontrol sensitif.', ['admin.data-maintenance.*']),
                    self::item('SLA antrean', 'admin.settings.sla.edit', 'i-clock', 'Atur batas waktu pemeriksaan setiap jenis permohonan.', ['admin.settings.sla.*']),
                    self::item('Audit log', 'admin.audit.index', 'i-file', 'Telusuri tindakan penting yang dilakukan pengguna sistem.', ['admin.audit.*']),
                    self::item('Antrean gagal', 'admin.failed-jobs.index', 'i-info', 'Periksa pekerjaan latar belakang yang gagal dan jalankan ulang.', ['admin.failed-jobs.*']),
                    self::item('Kesehatan sistem', 'admin.health.index', 'i-shield-check', 'Periksa koneksi layanan, queue, penyimpanan, dan integrasi.', ['admin.health.*']),
                    self::item('Panduan sistem', 'admin.guide.index', 'i-help', 'Baca alur kerja dan fungsi setiap menu administrasi.', ['admin.guide.*']),
                ],
            ],
            [
                'label' => 'Akun',
                'items' => [
                    self::item('Daftar akun', 'admin.accounts.index', 'i-user', 'Lihat seluruh akun serta aktifkan atau nonaktifkan akses pengguna.', ['admin.accounts.*']),
                    self::item('Profil akun', 'account.profile.edit', 'i-user', 'Perbarui nama, email, dan nomor HP akun Anda.', ['account.profile.*']),
                    self::item('Keamanan akun', 'account.security.index', 'i-lock', 'Kelola kata sandi, 2FA, sesi, dan riwayat login pribadi.', ['account.security.*']),
                ],
            ],
        ];
    }

    public static function workflow(): array
    {
        return [
            ['title' => 'Pengajuan', 'description' => 'Tenant atau pemohon tanpa akun mengisi formulir dan mengunggah dokumen yang diwajibkan.'],
            ['title' => 'Pemeriksaan divisi', 'description' => 'Permohonan loading diperiksa TR. Surat izin kerja diarahkan otomatis ke TR, Engineering, atau EP berdasarkan kategorinya.'],
            ['title' => 'Deposit bila diperlukan', 'description' => 'Management menetapkan deposit. Tenant mengunggah bukti pembayaran, lalu Finance memverifikasi dana yang masuk.'],
            ['title' => 'Keputusan dan surat', 'description' => 'Validator dapat menyetujui, menolak, atau meminta perbaikan. Surat resmi dan QR terbit setelah persetujuan.'],
            ['title' => 'Akses area', 'description' => 'Security memverifikasi QR dan mencatat waktu masuk serta keluar selama surat masih berlaku.'],
            ['title' => 'Pemantauan', 'description' => 'Admin memantau proses, komunikasi, keamanan, dan kesehatan layanan. Validasi darurat hanya digunakan saat divisi terkait berhalangan dan wajib disertai alasan.'],
        ];
    }

    private static function item(string $label, string $route, string $icon, string $description, array $active, ?string $badge = null): array
    {
        return compact('label', 'route', 'icon', 'description', 'active', 'badge');
    }
}
