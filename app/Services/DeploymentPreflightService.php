<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class DeploymentPreflightService
{
    public function __construct(
        private readonly Migrator $migrator,
        private readonly BackupCipher $backupCipher,
    ) {}

    /**
     * @return array<int, array{name: string, status: string, detail: string}>
     */
    public function run(bool $allowPendingMigrations = false): array
    {
        return [
            $this->check('Runtime PHP', fn (): array => $this->runtime()),
            $this->check('Konfigurasi aplikasi', fn (): array => $this->application()),
            $this->check('Database', fn (): array => $this->database()),
            $this->check('Migration', fn (): array => $this->migrations($allowPendingMigrations)),
            $this->check('Storage', fn (): array => $this->storage()),
            $this->check('Cache', fn (): array => $this->cache()),
            $this->check('Queue', fn (): array => $this->queue()),
            $this->check('Realtime', fn (): array => $this->realtime()),
            $this->check('Perlindungan dokumen', fn (): array => $this->documentProtection()),
        ];
    }

    /** @return array{status: string, detail: string} */
    private function runtime(): array
    {
        $required = ['ctype', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer'];
        $missing = array_values(array_filter($required, fn (string $extension): bool => ! extension_loaded($extension)));

        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            return $this->result('error', 'PHP 8.2 atau lebih baru diperlukan. Versi aktif: '.PHP_VERSION.'.');
        }

        if ($missing !== []) {
            return $this->result('error', 'Ekstensi PHP wajib belum tersedia: '.implode(', ', $missing).'.');
        }

        return $this->result('pass', 'PHP '.PHP_VERSION.' dan seluruh ekstensi wajib tersedia.');
    }

    /** @return array{status: string, detail: string} */
    private function application(): array
    {
        app('encrypter')->encryptString('preflight');

        if (! app()->environment('production')) {
            return $this->result('pass', 'Environment '.app()->environment().' terdeteksi; aturan production tidak dipaksakan.');
        }

        $issues = [];
        if (config('app.debug')) {
            $issues[] = 'APP_DEBUG harus false';
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $issues[] = 'APP_URL harus menggunakan HTTPS';
        }
        if (config('session.secure') !== true) {
            $issues[] = 'SESSION_SECURE_COOKIE harus true';
        }
        if (config('session.http_only') !== true) {
            $issues[] = 'SESSION_HTTP_ONLY harus true';
        }
        if (! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            $issues[] = 'SESSION_SAME_SITE harus lax atau strict';
        }
        if (! config('http_security.enabled')) {
            $issues[] = 'HTTP_SECURITY_HEADERS_ENABLED harus true';
        }
        if (! config('http_security.hsts.enabled')) {
            $issues[] = 'HTTP_HSTS_ENABLED harus true';
        }
        if (! config('http_security.csp.enabled')) {
            $issues[] = 'HTTP_CSP_ENABLED harus true';
        }
        if (config('http_security.csp.report_only')) {
            $issues[] = 'HTTP_CSP_REPORT_ONLY harus false';
        }

        $applicationHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $trustedHosts = array_map('strtolower', (array) config('http_security.trusted_hosts'));
        if ($applicationHost === '' || ! in_array($applicationHost, $trustedHosts, true)) {
            $issues[] = 'APP_TRUSTED_HOSTS harus memuat hostname APP_URL';
        }

        return $issues === []
            ? $this->result('pass', 'Debug nonaktif, HTTPS, cookie sesi, trusted host, dan header keamanan aktif.')
            : $this->result('error', implode('; ', $issues).'.');
    }

    /** @return array{status: string, detail: string} */
    private function database(): array
    {
        DB::connection()->getPdo();
        DB::select('SELECT 1');

        return $this->result('pass', 'Koneksi '.DB::connection()->getDriverName().' aktif dan query uji berhasil.');
    }

    /** @return array{status: string, detail: string} */
    private function migrations(bool $allowPending): array
    {
        if (! $this->migrator->repositoryExists()) {
            return $this->result('error', 'Tabel migration belum tersedia. Jalankan php artisan migrate --force.');
        }

        $files = array_keys($this->migrator->getMigrationFiles(database_path('migrations')));
        $pending = array_values(array_diff($files, $this->migrator->getRepository()->getRan()));

        if ($pending === []) {
            return $this->result('pass', 'Tidak ada migration tertunda.');
        }

        $detail = count($pending).' migration tertunda: '.implode(', ', array_slice($pending, 0, 3));
        if (count($pending) > 3) {
            $detail .= ', dan '.(count($pending) - 3).' lainnya';
        }

        return $this->result($allowPending ? 'warning' : 'error', $detail.'.');
    }

    /** @return array{status: string, detail: string} */
    private function storage(): array
    {
        $directories = [
            storage_path('app/private'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                return $this->result('error', 'Direktori aplikasi tidak dapat ditulis: '.$directory);
            }
        }

        $probe = storage_path('framework/.preflight-'.Str::random(12));
        file_put_contents($probe, 'ok', LOCK_EX);
        $readable = file_get_contents($probe) === 'ok';
        @unlink($probe);

        return $readable
            ? $this->result('pass', 'Direktori storage dan cache dapat dibaca serta ditulis.')
            : $this->result('error', 'File uji storage tidak dapat dibaca kembali.');
    }

    /** @return array{status: string, detail: string} */
    private function cache(): array
    {
        $key = 'deployment:preflight:'.Str::random(12);
        Cache::put($key, 'ok', 30);
        $works = Cache::get($key) === 'ok';
        Cache::forget($key);

        return $works
            ? $this->result('pass', 'Cache '.config('cache.default').' dapat membaca dan menulis data uji.')
            : $this->result('error', 'Cache gagal membaca kembali data uji.');
    }

    /** @return array{status: string, detail: string} */
    private function queue(): array
    {
        $connection = (string) config('queue.default');
        if (app()->environment('production') && in_array($connection, ['sync', 'null'], true)) {
            return $this->result('error', 'QUEUE_CONNECTION tidak boleh '.$connection.' di production.');
        }

        if ($connection === 'database') {
            $jobsTable = (string) config('queue.connections.database.table', 'jobs');
            $failedTable = (string) config('queue.failed.table', 'failed_jobs');
            if (! Schema::hasTable($jobsTable) || ! Schema::hasTable($failedTable)) {
                return $this->result('error', 'Tabel jobs atau failed_jobs belum tersedia.');
            }
        }

        return $this->result('pass', 'Queue menggunakan koneksi '.$connection.'.');
    }

    /** @return array{status: string, detail: string} */
    private function realtime(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return $this->result('warning', 'Broadcasting tidak menggunakan Reverb.');
        }

        $app = config('reverb.apps.apps.0', []);
        $required = [$app['app_id'] ?? null, $app['key'] ?? null, $app['secret'] ?? null, data_get($app, 'options.host')];
        if (collect($required)->contains(fn (mixed $value): bool => blank($value))) {
            return $this->result('error', 'Konfigurasi kredensial atau host Reverb belum lengkap.');
        }

        $origins = (array) ($app['allowed_origins'] ?? []);
        if (app()->environment('production') && ($origins === [] || in_array('*', $origins, true))) {
            return $this->result('error', 'REVERB_ALLOWED_ORIGINS harus berisi domain production yang spesifik.');
        }

        return $this->result('pass', 'Reverb terkonfigurasi dengan '.count($origins).' origin yang diizinkan.');
    }

    /** @return array{status: string, detail: string} */
    private function documentProtection(): array
    {
        $warnings = [];
        if (! config('malware.enabled')) {
            $warnings[] = 'pemindaian malware nonaktif';
        }
        if (! config('backup.enabled')) {
            $warnings[] = 'backup terenkripsi nonaktif';
        } elseif (! $this->backupCipher->hasValidConfiguredKey()) {
            return $this->result('error', 'Kunci backup terenkripsi tidak valid.');
        }

        return $warnings === []
            ? $this->result('pass', 'Pemindaian malware dan backup terenkripsi aktif.')
            : $this->result('warning', ucfirst(implode('; ', $warnings)).'.');
    }

    /** @return array{name: string, status: string, detail: string} */
    private function check(string $name, Closure $callback): array
    {
        try {
            return ['name' => $name, ...$callback()];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'name' => $name,
                'status' => 'error',
                'detail' => 'Pemeriksaan gagal: '.$exception->getMessage(),
            ];
        }
    }

    /** @return array{status: string, detail: string} */
    private function result(string $status, string $detail): array
    {
        return compact('status', 'detail');
    }
}
