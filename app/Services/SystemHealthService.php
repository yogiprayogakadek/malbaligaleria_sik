<?php

namespace App\Services;

use App\Models\DocumentTemplateSetting;
use App\Models\EmailMessageLog;
use App\Models\MailSetting;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemHealthService
{
    public function __construct(
        private readonly WahaClient $wahaClient,
        private readonly BackupCipher $backupCipher,
        private readonly LibreOfficeConverterClient $documentConverter,
    ) {}

    public function checks(): array
    {
        return [
            $this->database(),
            $this->cache(),
            $this->storage(),
            $this->backup(),
            $this->queue(),
            $this->mail(),
            $this->whatsapp(),
            $this->documentConverter(),
            $this->realtime(),
        ];
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->result('Database', 'healthy', 'Koneksi database aktif.');
        } catch (Throwable) {
            return $this->result('Database', 'error', 'Database tidak dapat dihubungi.');
        }
    }

    private function cache(): array
    {
        try {
            $key = 'system-health:cache-probe';
            Cache::put($key, 'ok', 30);
            $works = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $this->result('Cache', $works ? 'healthy' : 'error', $works ? 'Penyimpanan cache aktif.' : 'Cache tidak dapat membaca data uji.');
        } catch (Throwable) {
            return $this->result('Cache', 'error', 'Cache tidak dapat digunakan.');
        }
    }

    private function storage(): array
    {
        try {
            $path = 'health/.probe';
            Storage::disk('local')->put($path, 'ok');
            $works = Storage::disk('local')->get($path) === 'ok';
            Storage::disk('local')->delete($path);

            return $this->result('Penyimpanan dokumen', $works ? 'healthy' : 'error', $works ? 'Storage dapat membaca dan menulis.' : 'Storage gagal membaca data uji.');
        } catch (Throwable) {
            return $this->result('Penyimpanan dokumen', 'error', 'Storage tidak dapat membaca atau menulis.');
        }
    }

    private function queue(): array
    {
        if (config('queue.default') === 'sync') {
            return $this->result('Queue worker', 'warning', 'Queue menggunakan mode sync; pekerjaan berjalan di request web.');
        }

        try {
            $heartbeat = Cache::get('system-health:queue-worker');
            $lastSeen = $heartbeat ? CarbonImmutable::parse($heartbeat) : null;
            $pending = DB::table(config('queue.connections.database.table', 'jobs'))->count();
            $failed = DB::table(config('queue.failed.table', 'failed_jobs'))->count();
            $fresh = $lastSeen?->greaterThan(now()->subMinutes(3)) ?? false;
            $status = $fresh && $failed === 0 ? 'healthy' : ($fresh ? 'warning' : 'error');
            $detail = $fresh
                ? "Worker aktif; {$pending} antre dan {$failed} gagal."
                : "Heartbeat worker belum diterima; {$pending} pekerjaan menunggu.";

            return $this->result('Queue worker', $status, $detail, $lastSeen?->diffForHumans());
        } catch (Throwable) {
            return $this->result('Queue worker', 'error', 'Status queue tidak dapat dibaca.');
        }
    }

    private function backup(): array
    {
        if (! config('backup.enabled')) {
            return $this->result('Backup terenkripsi', 'warning', 'Backup terjadwal belum diaktifkan.');
        }

        if (! $this->backupCipher->hasValidConfiguredKey()) {
            return $this->result('Backup terenkripsi', 'error', 'Kunci enkripsi backup belum dikonfigurasi dengan benar.');
        }

        try {
            $path = rtrim((string) config('backup.path'), '\\/');
            $files = glob($path.DIRECTORY_SEPARATOR.'*.mbgbak') ?: [];
            usort($files, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));
            if ($files === []) {
                return $this->result('Backup terenkripsi', 'error', 'Belum ada backup yang berhasil dibuat.');
            }

            $latest = CarbonImmutable::createFromTimestamp(filemtime($files[0]));
            $restoreMarker = $path.DIRECTORY_SEPARATOR.'.last-restore-test';
            $restoreTest = is_file($restoreMarker)
                ? CarbonImmutable::parse(trim((string) file_get_contents($restoreMarker)))
                : null;
            $fresh = $latest->greaterThan(now()->subHours(26));
            $restoreFresh = $restoreTest?->greaterThan(now()->subDays(8)) ?? false;
            $status = $fresh && $restoreFresh ? 'healthy' : ($fresh ? 'warning' : 'error');
            $detail = $restoreFresh
                ? 'Backup terbaru tersedia dan uji restore mingguan berhasil.'
                : 'Backup tersedia, tetapi uji restore belum berhasil dalam 8 hari terakhir.';

            return $this->result('Backup terenkripsi', $status, $detail, $latest->diffForHumans());
        } catch (Throwable) {
            return $this->result('Backup terenkripsi', 'error', 'Status backup tidak dapat dibaca.');
        }
    }

    private function mail(): array
    {
        $setting = MailSetting::current();

        if (! $setting?->is_active) {
            return $this->result('Email', 'warning', 'Pengiriman email belum diaktifkan.');
        }

        $failed = EmailMessageLog::query()
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        return $this->result(
            'Email',
            $failed > 0 ? 'warning' : 'healthy',
            "Konfigurasi aktif melalui {$setting->host}:{$setting->port}; {$failed} pesan gagal dalam 24 jam."
        );
    }

    private function whatsapp(): array
    {
        $setting = WhatsappSetting::current();
        if (! $setting?->is_active) {
            return $this->result('WhatsApp', 'warning', 'Integrasi WAHA belum diaktifkan.');
        }

        try {
            $session = $this->wahaClient->sessionStatus($setting);
            $working = ($session['status'] ?? null) === 'WORKING';
            $failed = WhatsappMessageLog::query()->where('status', 'failed')->where('updated_at', '>=', now()->subDay())->count();

            return $this->result(
                'WhatsApp',
                $working && $failed === 0 ? 'healthy' : ($working ? 'warning' : 'error'),
                $working ? "Sesi aktif; {$failed} pesan gagal dalam 24 jam." : 'Sesi WAHA tidak dalam status WORKING.',
            );
        } catch (Throwable) {
            return $this->result('WhatsApp', 'error', 'Server WAHA atau sesi tidak dapat dihubungi.');
        }
    }

    private function realtime(): array
    {
        $configured = config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'))
            && filled(config('broadcasting.connections.reverb.options.host'));

        return $this->result(
            'Realtime',
            $configured ? 'healthy' : 'warning',
            $configured ? 'Konfigurasi Reverb tersedia.' : 'Konfigurasi Reverb belum lengkap.',
        );
    }

    private function documentConverter(): array
    {
        $setting = DocumentTemplateSetting::current();
        if (! $setting->exists || ! $setting->converter_enabled) {
            return $this->result('Document converter', 'warning', 'Renderer LibreOffice belum diaktifkan. Template bawaan tetap digunakan.');
        }

        try {
            $health = $this->documentConverter->health($setting);
            $version = filled($health['version'] ?? null) ? ' LibreOffice '.$health['version'] : '';

            return $this->result('Document converter', 'healthy', 'Layanan'.$version.' aktif di jaringan privat.');
        } catch (Throwable) {
            return $this->result(
                'Document converter',
                $setting->fallback_enabled ? 'warning' : 'error',
                $setting->fallback_enabled
                    ? 'Converter tidak dapat dihubungi; template bawaan akan digunakan otomatis.'
                    : 'Converter tidak dapat dihubungi dan fallback dinonaktifkan.',
            );
        }
    }

    private function result(string $name, string $status, string $detail, ?string $lastSeen = null): array
    {
        return compact('name', 'status', 'detail', 'lastSeen');
    }
}
