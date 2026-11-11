<?php

namespace App\Console\Commands;

use App\Models\WorkPermit;
use App\Services\EventWhatsappGroupDispatcher;
use Illuminate\Console\Command;

class DispatchApprovedEventWhatsappGroup extends Command
{
    protected $signature = 'event-whatsapp-group:dispatch {permit : Nomor surat atau ID permohonan Event}';

    protected $description = 'Membuat pengiriman grup WhatsApp untuk Surat Izin Event yang sudah disetujui';

    public function handle(EventWhatsappGroupDispatcher $dispatcher): int
    {
        $reference = trim((string) $this->argument('permit'));
        $permit = WorkPermit::query()
            ->where('permit_kind', 'event')
            ->where(function ($query) use ($reference): void {
                $query->where('permit_number', $reference);
                if (ctype_digit($reference)) {
                    $query->orWhereKey((int) $reference);
                }
            })
            ->first();

        if (! $permit) {
            $this->error('Permohonan Event tidak ditemukan.');

            return self::FAILURE;
        }
        if ($permit->status !== 'approved' || $permit->revoked_at) {
            $this->error('Surat Event harus berstatus approved dan tidak dicabut.');

            return self::FAILURE;
        }

        $delivery = $dispatcher->approved($permit);
        if (! $delivery) {
            $this->error('Pengiriman tidak dibuat. Pastikan WAHA dan pengiriman grup Event aktif.');

            return self::FAILURE;
        }
        if (! $delivery->wasRecentlyCreated) {
            $this->warn('Pengiriman sudah tercatat. Gunakan tombol Kirim ulang pada riwayat pengiriman.');

            return self::SUCCESS;
        }

        $this->info("Pengiriman {$permit->permit_number} dimasukkan ke antrean.");

        return self::SUCCESS;
    }
}
