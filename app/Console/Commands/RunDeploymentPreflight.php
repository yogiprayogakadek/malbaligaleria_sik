<?php

namespace App\Console\Commands;

use App\Services\DeploymentPreflightService;
use Illuminate\Console\Command;

class RunDeploymentPreflight extends Command
{
    protected $signature = 'app:preflight {--before-migrate : Izinkan migration tertunda sebelum tahap migrate}';

    protected $description = 'Memeriksa kesiapan dan keamanan konfigurasi sebelum aplikasi melayani traffic';

    public function handle(DeploymentPreflightService $preflight): int
    {
        $results = collect($preflight->run((bool) $this->option('before-migrate')));
        $labels = ['pass' => 'LULUS', 'warning' => 'PERINGATAN', 'error' => 'GAGAL'];

        $this->table(
            ['Pemeriksaan', 'Status', 'Keterangan'],
            $results->map(fn (array $result): array => [
                $result['name'],
                $labels[$result['status']] ?? strtoupper($result['status']),
                $result['detail'],
            ])->all(),
        );

        $errors = $results->where('status', 'error')->count();
        $warnings = $results->where('status', 'warning')->count();

        if ($errors > 0) {
            $this->error("Preflight gagal dengan {$errors} masalah yang harus diperbaiki.");

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->warn("Preflight lulus dengan {$warnings} peringatan.");
        } else {
            $this->info('Preflight lulus tanpa peringatan.');
        }

        return self::SUCCESS;
    }
}
