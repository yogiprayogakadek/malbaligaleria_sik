<?php

namespace App\Console\Commands;

use App\Services\EncryptedBackupManager;
use Illuminate\Console\Command;
use Throwable;

class CreateEncryptedBackup extends Command
{
    protected $signature = 'backup:create';

    protected $description = 'Membuat backup database dan dokumen privat yang terenkripsi';

    public function handle(EncryptedBackupManager $backups): int
    {
        try {
            $path = $backups->create();
            $this->info('Backup terenkripsi berhasil dibuat dan diverifikasi: '.basename($path));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
