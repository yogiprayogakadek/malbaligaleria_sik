<?php

namespace Tests\Unit;

use App\Services\BackupCipher;
use App\Services\EncryptedBackupManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class EncryptedBackupTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing-backup/'.Str::uuid());
        File::makeDirectory($this->directory, 0700, true);
        config([
            'backup.encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
            'backup.path' => $this->directory.'/backups',
            'backup.temporary_path' => $this->directory.'/temporary',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_large_backup_round_trips_and_rejects_tampering(): void
    {
        $plain = $this->directory.'/plain.bin';
        $encrypted = $this->directory.'/backup.mbgbak';
        $restored = $this->directory.'/restored.bin';
        File::put($plain, random_bytes(1048576 + 37));

        $cipher = app(BackupCipher::class);
        $cipher->encrypt($plain, $encrypted);
        $cipher->decrypt($encrypted, $restored);

        $this->assertSame(hash_file('sha256', $plain), hash_file('sha256', $restored));

        $handle = fopen($encrypted, 'r+b');
        fseek($handle, 64);
        $byte = fread($handle, 1);
        fseek($handle, 64);
        fwrite($handle, chr(ord($byte) ^ 0xFF));
        fclose($handle);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Integritas atau kunci backup tidak valid.');
        $cipher->decrypt($encrypted, $this->directory.'/tampered.bin');
    }

    public function test_restore_test_validates_and_extracts_database_and_documents(): void
    {
        $archive = $this->directory.'/source.zip';
        $encrypted = $this->directory.'/valid.mbgbak';
        $manifest = [
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'database_driver' => 'mysql',
            'database_entry' => 'database/database.sql',
            'document_count' => 1,
        ];
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL));
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->addFromString('database/database.sql', 'CREATE TABLE backup_test (id INT);');
        $zip->addFromString('documents/identity/test.jpg', 'document-content');
        $zip->close();

        app(BackupCipher::class)->encrypt($archive, $encrypted);
        $restoredManifest = app(EncryptedBackupManager::class)->testRestore($encrypted);

        $this->assertSame('mysql', $restoredManifest['database_driver']);
        $this->assertSame(1, $restoredManifest['document_count']);
        $this->assertFileExists($this->directory.'/backups/.last-restore-test');

        config(['backup.restore_path' => $this->directory.'/restore-staging']);
        $staged = app(EncryptedBackupManager::class)->stageRestore($encrypted);
        $this->assertFileExists($staged['path'].'/database/database.sql');
        $this->assertSame('document-content', File::get($staged['path'].'/documents/identity/test.jpg'));
    }

    public function test_restore_rejects_archive_path_traversal(): void
    {
        $archive = $this->directory.'/unsafe.zip';
        $encrypted = $this->directory.'/unsafe.mbgbak';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL));
        $zip->addFromString('manifest.json', json_encode([
            'database_entry' => 'database/database.sql',
        ], JSON_THROW_ON_ERROR));
        $zip->addFromString('database/database.sql', 'SELECT 1;');
        $zip->addFromString('../outside.txt', 'unsafe');
        $zip->close();
        app(BackupCipher::class)->encrypt($archive, $encrypted);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Arsip mengandung path yang tidak aman.');
        app(EncryptedBackupManager::class)->testRestore($encrypted);
    }
}
