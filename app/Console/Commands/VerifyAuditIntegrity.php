<?php

namespace App\Console\Commands;

use App\Services\AuditIntegrityService;
use Illuminate\Console\Command;

class VerifyAuditIntegrity extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Memverifikasi hash berantai pada seluruh audit log';

    public function handle(AuditIntegrityService $integrity): int
    {
        $result = $integrity->verify();
        if (! $result['valid']) {
            $this->error('Integritas audit gagal pada ID '.($result['first_invalid_id'] ?? 'tidak diketahui').'. '.$result['reason']);

            return self::FAILURE;
        }

        $this->info("Integritas {$result['count']} audit log terverifikasi.");

        return self::SUCCESS;
    }
}
