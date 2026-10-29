<?php

namespace App\Services;

use App\Jobs\SendApprovedEventWhatsappGroup;
use App\Models\EventWhatsappGroupDelivery;
use App\Models\EventWhatsappGroupSetting;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class EventWhatsappGroupDispatcher
{
    public function __construct(private EventWhatsappGroupMessageRenderer $renderer) {}

    public function approved(WorkPermit $permit): ?EventWhatsappGroupDelivery
    {
        $setting = EventWhatsappGroupSetting::current();
        if (! $permit->isEventPermit() || $permit->status !== 'approved' || $permit->revoked_at || ! $setting?->is_active || ! WhatsappSetting::current()?->is_active) {
            return null;
        }

        $rendered = $this->renderer->render($permit, $setting->base_message);
        $organizer = trim((string) preg_replace('~[\\\\/:*?"<>|\x00-\x1F]+~u', ' ', $permit->contractor_name));
        $filename = Str::limit('Surat Izin Event - '.($organizer ?: 'EO Tenant'), 170, '').'.pdf';
        $uniqueKey = hash('sha256', "event-group-approved:{$permit->id}:{$permit->official_document_sha256}");

        try {
            $delivery = EventWhatsappGroupDelivery::query()->firstOrCreate(
                ['unique_key' => $uniqueKey],
                [
                    'work_permit_id' => $permit->id,
                    'setting_id' => $setting->id,
                    'group_id' => $setting->group_id,
                    'group_id_hash' => hash('sha256', $setting->group_id),
                    'message' => $rendered['message'],
                    'mentions' => $rendered['mentions'],
                    'filename' => $filename,
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return EventWhatsappGroupDelivery::query()->where('work_permit_id', $permit->id)->first();
        }

        if ($delivery->wasRecentlyCreated) {
            SendApprovedEventWhatsappGroup::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }
}
