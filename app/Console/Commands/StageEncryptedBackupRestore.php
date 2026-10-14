<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupManager;
use Illuminate\Console\Command;
use Throwable;

class StageEncryptedBackupRestore extends Command
{
    protected $signature = 'backup:restore-stage {file? : File .mbgbak; kosongkan untuk memakai backup terbaru}';

    protected $description = 'Mendekripsi backup ke staging privat tanpa menimpa data aktif';

    public function handle(EncryptedBackupManager $backups): int
    {
        try {
            $result = $backups->stageRestore($this->argument('file'));
            $manifest = $result['manifest'];
            $this->info("Backup {$manifest['database_driver']} berhasil dipulihkan ke staging privat:");
            $this->line($result['path']);
            $this->warn('Periksa hasil staging sebelum memuat database atau mengganti dokumen aktif.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
