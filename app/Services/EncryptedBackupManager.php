<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class EncryptedBackupManager
{
    public function __construct(private readonly BackupCipher $cipher) {}

    public function create(): string
    {
        if (! config('backup.enabled')) {
            throw new RuntimeException('Backup belum diaktifkan melalui BACKUP_ENABLED.');
        }

        $this->ensureDirectory($this->backupPath());
        $workDirectory = $this->newWorkDirectory();
        $archivePath = $workDirectory.'/backup.zip';
        $destination = $this->backupPath().DIRECTORY_SEPARATOR.'sik-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.mbgbak';

        try {
            $manifest = $this->buildArchive($archivePath, $workDirectory);
            $this->cipher->encrypt($archivePath, $destination);
            $verified = $this->verify($destination);

            if (($verified['created_at'] ?? null) !== $manifest['created_at']) {
                throw new RuntimeException('Manifest hasil verifikasi tidak cocok.');
            }

            $this->prune();

            return $destination;
        } catch (\Throwable $exception) {
            File::delete($destination);
            throw $exception;
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    public function verify(string $encryptedPath): array
    {
        $workDirectory = $this->newWorkDirectory();
        $archivePath = $workDirectory.'/verified.zip';

        try {
            $this->cipher->decrypt($encryptedPath, $archivePath);

            return $this->inspectArchive($archivePath);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    public function testRestore(?string $encryptedPath = null): array
    {
        $encryptedPath ??= $this->latest();
        if (! $encryptedPath) {
            throw new RuntimeException('Belum ada backup yang dapat diuji.');
        }

        $workDirectory = $this->newWorkDirectory();
        $archivePath = $workDirectory.'/restore.zip';
        $restoreDirectory = $workDirectory.'/restored';

        try {
            $this->cipher->decrypt($encryptedPath, $archivePath);
            $manifest = $this->inspectArchive($archivePath);
            $this->extractSafely($archivePath, $restoreDirectory);

            $databaseEntry = $manifest['database_entry'];
            if (! is_file($restoreDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $databaseEntry))) {
                throw new RuntimeException('Snapshot database tidak ditemukan setelah uji restore.');
            }

            $this->ensureDirectory($this->backupPath());
            File::put($this->backupPath().DIRECTORY_SEPARATOR.'.last-restore-test', now()->toIso8601String());

            return $manifest;
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    public function stageRestore(?string $encryptedPath = null): array
    {
        $encryptedPath ??= $this->latest();
        if (! $encryptedPath) {
            throw new RuntimeException('Belum ada backup yang dapat dipulihkan.');
        }

        $restoreRoot = rtrim((string) config('backup.restore_path'), '\\/');
        $this->ensureDirectory($restoreRoot);
        $destination = $restoreRoot.DIRECTORY_SEPARATOR.'restore-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $workDirectory = $this->newWorkDirectory();
        $archivePath = $workDirectory.'/restore.zip';

        try {
            $this->cipher->decrypt($encryptedPath, $archivePath);
            $manifest = $this->inspectArchive($archivePath);
            $this->extractSafely($archivePath, $destination);

            return ['path' => $destination, 'manifest' => $manifest];
        } catch (\Throwable $exception) {
            File::deleteDirectory($destination);
            throw $exception;
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    public function latest(): ?string
    {
        $files = glob($this->backupPath().DIRECTORY_SEPARATOR.'*.mbgbak') ?: [];
        usort($files, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        return $files[0] ?? null;
    }

    private function buildArchive(string $archivePath, string $workDirectory): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException('Arsip backup sementara tidak dapat dibuat.');
        }

        try {
            [$databasePath, $databaseEntry, $databaseDriver] = $this->databaseSnapshot($workDirectory);
            if (! $zip->addFile($databasePath, $databaseEntry)) {
                throw new RuntimeException('Snapshot database tidak dapat ditambahkan ke arsip.');
            }

            $documentCount = $this->addDocuments($zip);
            $manifest = [
                'version' => 1,
                'created_at' => now()->toIso8601String(),
                'database_driver' => $databaseDriver,
                'database_entry' => $databaseEntry,
                'document_count' => $documentCount,
                'application' => 'SIK Mal Bali Galeria',
            ];

            if (! $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Manifest backup tidak dapat ditambahkan.');
            }
        } finally {
            $closed = $zip->close();
        }

        if (! $closed) {
            throw new RuntimeException('Arsip backup tidak dapat diselesaikan.');
        }

        return $manifest;
    }

    private function databaseSnapshot(string $workDirectory): array
    {
        $connection = config('database.default');
        $configuration = config("database.connections.{$connection}");
        $driver = $configuration['driver'] ?? null;

        if ($driver === 'sqlite') {
            $databasePath = (string) ($configuration['database'] ?? '');
            if ($databasePath === '' || $databasePath === ':memory:') {
                throw new RuntimeException('Database SQLite in-memory tidak dapat dibackup.');
            }

            $snapshotPath = $workDirectory.'/database.sqlite';
            $pdo = DB::connection($connection)->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($snapshotPath));

            return [$snapshotPath, 'database/database.sqlite', 'sqlite'];
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $snapshotPath = $workDirectory.'/database.sql';
            $this->dumpMysql($configuration, $snapshotPath);

            return [$snapshotPath, 'database/database.sql', $driver];
        }

        throw new RuntimeException("Driver database {$driver} belum didukung oleh backup.");
    }

    private function dumpMysql(array $configuration, string $destination): void
    {
        $command = [
            (string) config('backup.mysqldump_binary', 'mysqldump'),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--default-character-set='.(string) ($configuration['charset'] ?? 'utf8mb4'),
            '--host='.(string) ($configuration['host'] ?? '127.0.0.1'),
            '--port='.(string) ($configuration['port'] ?? '3306'),
            '--user='.(string) ($configuration['username'] ?? ''),
            (string) ($configuration['database'] ?? ''),
        ];
        $output = @fopen($destination, 'xb');
        if ($output === false) {
            throw new RuntimeException('File dump database tidak dapat dibuat.');
        }

        $error = '';
        $process = new Process($command, null, ['MYSQL_PWD' => (string) ($configuration['password'] ?? '')]);
        $process->setTimeout(900);

        try {
            $process->run(function (string $type, string $buffer) use ($output, &$error): void {
                if ($type === Process::OUT) {
                    fwrite($output, $buffer);
                } else {
                    $error .= $buffer;
                }
            });
        } finally {
            fclose($output);
        }

        if (! $process->isSuccessful()) {
            File::delete($destination);
            throw new RuntimeException('mysqldump gagal: '.Str::limit(trim($error), 300));
        }
    }

    private function addDocuments(ZipArchive $zip): int
    {
        $root = realpath((string) config('backup.documents_path'));
        if ($root === false || ! is_dir($root)) {
            return 0;
        }

        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->isLink()) {
                continue;
            }

            $path = $file->getRealPath();
            if ($path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Dokumen backup berada di luar storage privat.');
            }

            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
            if (! $zip->addFile($path, 'documents/'.$relative)) {
                throw new RuntimeException("Dokumen tidak dapat ditambahkan ke backup: {$relative}");
            }
            $count++;
        }

        return $count;
    }

    private function inspectArchive(string $archivePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Arsip hasil dekripsi rusak atau tidak konsisten.');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $this->assertSafeEntry((string) $zip->getNameIndex($index));
            }

            $json = $zip->getFromName('manifest.json');
            if ($json === false) {
                throw new RuntimeException('Manifest tidak ditemukan dalam backup.');
            }

            $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            $databaseEntry = $manifest['database_entry'] ?? null;
            if (! is_string($databaseEntry) || $zip->locateName($databaseEntry) === false) {
                throw new RuntimeException('Snapshot database tidak ditemukan dalam backup.');
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    private function extractSafely(string $archivePath, string $destination): void
    {
        $this->ensureDirectory($destination);
        $zip = new ZipArchive;
        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('Arsip restore tidak dapat dibuka.');
        }

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = (string) $zip->getNameIndex($index);
                $this->assertSafeEntry($entry);
                if (str_ends_with($entry, '/')) {
                    continue;
                }

                $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $entry);
                $this->ensureDirectory(dirname($target));
                $input = $zip->getStream($entry);
                $output = @fopen($target, 'xb');
                if ($input === false || $output === false) {
                    if (is_resource($input)) {
                        fclose($input);
                    }
                    throw new RuntimeException("File restore tidak dapat dibuat: {$entry}");
                }

                stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);
            }
        } finally {
            $zip->close();
        }
    }

    private function assertSafeEntry(string $entry): void
    {
        $normalized = str_replace('\\', '/', $entry);
        $allowed = $normalized === 'manifest.json'
            || str_starts_with($normalized, 'database/')
            || str_starts_with($normalized, 'documents/');

        if (! $allowed || str_starts_with($normalized, '/') || preg_match('/(^|\/)\.\.($|\/)/', $normalized) === 1 || preg_match('/^[A-Za-z]:/', $normalized) === 1) {
            throw new RuntimeException('Arsip mengandung path yang tidak aman.');
        }
    }

    private function prune(): void
    {
        $cutoff = now()->subDays(max(1, (int) config('backup.retention_days', 14)))->getTimestamp();
        foreach (glob($this->backupPath().DIRECTORY_SEPARATOR.'*.mbgbak') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                File::delete($file);
            }
        }
    }

    private function newWorkDirectory(): string
    {
        $directory = rtrim((string) config('backup.temporary_path'), '\\/').DIRECTORY_SEPARATOR.Str::uuid();
        $this->ensureDirectory($directory);

        return $directory;
    }

    private function backupPath(): string
    {
        return rtrim((string) config('backup.path'), '\\/');
    }

    private function ensureDirectory(string $directory): void
    {
        if (! File::isDirectory($directory) && ! File::makeDirectory($directory, 0700, true)) {
            throw new RuntimeException("Direktori tidak dapat dibuat: {$directory}");
        }
    }
}
