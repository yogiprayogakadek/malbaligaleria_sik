<?php

namespace App\Console\Commands;

use App\Models\DocumentRetentionSetting;
use App\Services\SensitiveDocumentRetentionService;
use Illuminate\Console\Command;

class PurgeSensitiveDocuments extends Command
{
    protected $signature = 'permits:purge-sensitive-documents {--dry-run : Hitung kandidat tanpa menghapus file}';

    protected $description = 'Menghapus dokumen sensitif yang melewati masa retensi';

    public function handle(SensitiveDocumentRetentionService $retention): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (! $dryRun && ! DocumentRetentionSetting::current()->enabled) {
            $this->info('Penghapusan otomatis dokumen sensitif sedang nonaktif.');

            return self::SUCCESS;
        }

        $result = $retention->run($dryRun);
        $this->table(['Kandidat', 'Dihapus', 'File tidak ditemukan', 'Gagal'], [[
            $result['eligible'],
            $result['purged'],
            $result['missing'],
            $result['failed'],
        ]]);

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
