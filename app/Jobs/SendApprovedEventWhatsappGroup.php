<?php

namespace App\Jobs;

use App\Models\EventWhatsappGroupDelivery;
use App\Models\WhatsappSetting;
use App\Services\PermitPdfService;
use App\Services\WahaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendApprovedEventWhatsappGroup implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(WahaClient $client, PermitPdfService $pdf): void
    {
        $delivery = EventWhatsappGroupDelivery::query()->with(['permit', 'setting'])->find($this->deliveryId);
        $whatsapp = WhatsappSetting::current();
        if (! $delivery || ($delivery->text_status === 'sent' && $delivery->file_status === 'sent')) {
            return;
        }
        if (! $whatsapp?->is_active || ! $delivery->setting?->is_active) {
            $delivery->update(['text_status' => 'skipped', 'file_status' => 'skipped', 'error' => 'Notifikasi grup Event tidak aktif.']);

            return;
        }

        $permit = $delivery->permit;
        if (! $permit?->isEventPermit() || $permit->status !== 'approved' || $permit->revoked_at) {
            $delivery->update(['text_status' => 'skipped', 'file_status' => 'skipped', 'error' => 'Surat Event tidak lagi berstatus disetujui.']);

            return;
        }

        $delivery->increment('attempts');
        $delivery->update(['error' => null]);

        try {
            if ($delivery->text_status !== 'sent') {
                $delivery->update(['text_status' => 'processing']);
                $result = $client->sendGroupText($whatsapp, $delivery->group_id, $delivery->message, $delivery->mentions ?: []);
                $delivery->update(['text_status' => 'sent', 'text_provider_message_id' => $this->providerId($result)]);
            }

            if ($delivery->file_status !== 'sent') {
                $delivery->update(['file_status' => 'processing']);
                $sealed = $pdf->assertWorkPermitIntegrity($permit);
                $contents = Storage::disk('local')->get($sealed->official_document_path);
                $result = $client->sendGroupFile(
                    $whatsapp,
                    $delivery->group_id,
                    $delivery->filename,
                    $contents,
                    pathinfo($delivery->filename, PATHINFO_FILENAME),
                );
                $delivery->update(['file_status' => 'sent', 'file_provider_message_id' => $this->providerId($result)]);
            }

            $delivery->update(['sent_at' => now()]);
        } catch (Throwable $exception) {
            $delivery->refresh();
            $delivery->update([
                'text_status' => $delivery->text_status === 'processing' ? 'failed' : $delivery->text_status,
                'file_status' => $delivery->file_status === 'processing' ? 'failed' : $delivery->file_status,
                'error' => str($exception->getMessage())->limit(500),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        EventWhatsappGroupDelivery::query()->whereKey($this->deliveryId)->update([
            'error' => str($exception?->getMessage() ?: 'Pengiriman grup gagal setelah beberapa percobaan.')->limit(500),
        ]);
        Log::warning('Notifikasi grup Surat Izin Event gagal dikirim.', ['delivery_id' => $this->deliveryId, 'exception' => $exception?->getMessage()]);
    }

    private function providerId(array $result): ?string
    {
        $id = data_get($result, 'id.id') ?? data_get($result, 'id') ?? data_get($result, 'key.id');

        return is_scalar($id) ? (string) $id : null;
    }
}
