<?php

namespace App\Services;

use App\Jobs\SendAnnouncementEmail;
use App\Jobs\SendAnnouncementWhatsapp;
use App\Models\AnnouncementCampaign;
use App\Models\AnnouncementRecipient;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnouncementCampaignService
{
    public function create(array $data, User $admin): AnnouncementCampaign
    {
        $tokenHash = hash('sha256', $data['submission_token']);
        $existing = AnnouncementCampaign::query()->where('submission_token_hash', $tokenHash)->first();
        if ($existing) {
            return $existing;
        }

        if (AnnouncementCampaign::query()->whereIn('status', ['queued', 'processing'])->exists()) {
            throw ValidationException::withMessages(['audiences' => 'Masih ada kampanye yang berjalan. Tunggu hingga selesai atau batalkan kampanye tersebut.']);
        }

        if (in_array('whatsapp', $data['channels'], true) && ! WhatsappSetting::current()?->is_active) {
            throw ValidationException::withMessages(['channels' => 'Konfigurasi WhatsApp belum aktif.']);
        }

        $recipients = $this->resolveRecipients($data['channels'], $data['audiences']);
        if ($recipients === []) {
            throw ValidationException::withMessages(['audiences' => 'Tidak ada penerima valid pada kelompok dan kanal yang dipilih.']);
        }

        $maximum = max(1, (int) config('announcements.max_recipients', 2000));
        if (count($recipients) > $maximum) {
            throw ValidationException::withMessages([
                'audiences' => 'Jumlah tujuan mencapai '.number_format(count($recipients), 0, ',', '.')." dan melewati batas {$maximum}. Persempit kanal atau kelompok penerima.",
            ]);
        }

        try {
            $campaign = DB::transaction(function () use ($data, $admin, $tokenHash, $recipients): AnnouncementCampaign {
                $campaign = AnnouncementCampaign::query()->create([
                    'created_by' => $admin->id,
                    'submission_token_hash' => $tokenHash,
                    'title' => trim($data['title']),
                    'message' => trim($data['message']),
                    'channels' => array_values(array_unique($data['channels'])),
                    'audiences' => array_values(array_unique($data['audiences'])),
                    'recipient_count' => count($recipients),
                ]);

                foreach ($recipients as $recipient) {
                    $campaign->recipients()->create($recipient);
                }

                return $campaign;
            });
        } catch (UniqueConstraintViolationException) {
            return AnnouncementCampaign::query()->where('submission_token_hash', $tokenHash)->firstOrFail();
        }

        $this->dispatch($campaign);

        return $campaign;
    }

    public function cancel(AnnouncementCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign): void {
            $locked = AnnouncementCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (in_array($locked->status, ['completed', 'cancelled'], true)) {
                return;
            }

            $locked->recipients()->where('status', 'queued')->update(['status' => 'cancelled']);
            $locked->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
            $locked->refreshProgress();
        });
    }

    private function resolveRecipients(array $channels, array $audiences): array
    {
        $resolved = [];
        $add = function (string $channel, string $audience, ?User $user, ?string $name, ?string $destination) use (&$resolved): void {
            $normalized = $channel === 'whatsapp'
                ? IndonesianPhoneNumber::normalize($destination)
                : $this->normalizeEmail($destination);
            if (! $normalized) {
                return;
            }

            $hash = hash('sha256', $normalized);
            $key = $channel.':'.$hash;
            $resolved[$key] ??= [
                'channel' => $channel,
                'audience' => $audience,
                'user_id' => $user?->id,
                'recipient_name' => filled($name) ? trim((string) $name) : null,
                'destination' => $normalized,
                'destination_hash' => $hash,
                'status' => 'queued',
            ];
        };

        if (in_array('accounts', $audiences, true)) {
            User::query()->where('is_active', true)->orderBy('id')->each(function (User $user) use ($channels, $add): void {
                foreach ($channels as $channel) {
                    $add($channel, 'accounts', $user, $user->name, $channel === 'whatsapp' ? $user->phone : $user->email);
                }
            });
        }

        if (in_array('applicants', $audiences, true)) {
            foreach ([LoadingPermit::query(), WorkPermit::query()] as $query) {
                $query->select(['id', 'applicant_name', 'applicant_phone', 'applicant_email'])
                    ->orderBy('id')
                    ->each(function ($permit) use ($channels, $add): void {
                        foreach ($channels as $channel) {
                            $add($channel, 'applicants', null, $permit->applicant_name, $channel === 'whatsapp' ? $permit->applicant_phone : $permit->applicant_email);
                        }
                    });
            }
        }

        return array_values($resolved);
    }

    private function dispatch(AnnouncementCampaign $campaign): void
    {
        $elapsed = ['whatsapp' => 0, 'email' => 0];
        $intervals = [
            'whatsapp' => max(15, (int) config('announcements.whatsapp_interval_seconds', 20)),
            'email' => max(1, (int) config('announcements.email_interval_seconds', 2)),
        ];

        $campaign->recipients()->orderBy('id')->each(function (AnnouncementRecipient $recipient) use (&$elapsed, $intervals): void {
            $jitter = $recipient->channel === 'whatsapp' ? $recipient->id % 5 : 0;
            $elapsed[$recipient->channel] += $intervals[$recipient->channel] + $jitter;

            $job = $recipient->channel === 'whatsapp'
                ? new SendAnnouncementWhatsapp($recipient->id)
                : new SendAnnouncementEmail($recipient->id);

            dispatch($job->delay(now()->addSeconds($elapsed[$recipient->channel]))->afterCommit());
        });
    }

    private function normalizeEmail(?string $email): ?string
    {
        $normalized = mb_strtolower(trim((string) $email));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) ? $normalized : null;
    }
}
