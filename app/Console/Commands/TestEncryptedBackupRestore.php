<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupManager;
use Illuminate\Console\Command;
use Throwable;

class TestEncryptedBackupRestore extends Command
{
    protected $signature = 'backup:test-restore {file? : File .mbgbak; kosongkan untuk memakai backup terbaru}';

    protected $description = 'Menguji dekripsi dan ekstraksi backup tanpa mengubah data aktif';

    public function handle(EncryptedBackupManager $backups): int
    {
        try {
            $manifest = $backups->testRestore($this->argument('file'));
            $this->info("Uji restore berhasil. Snapshot {$manifest['database_driver']} dengan {$manifest['document_count']} dokumen dapat dipulihkan.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
