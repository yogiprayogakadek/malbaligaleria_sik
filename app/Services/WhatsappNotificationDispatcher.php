<?php

namespace App\Services;

use App\Jobs\SendWhatsappMessage;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappSetting;
use App\Models\WorkPermit;
use App\Support\ApplicantStatusUrl;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

class WhatsappNotificationDispatcher
{
    public function passwordReset(User $user, string $url): void
    {
        $setting = WhatsappSetting::current();
        if (! $setting?->is_active || ! IndonesianPhoneNumber::normalize($user->phone)) {
            return;
        }

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $message = implode("\n", [
            '*Mal Bali Galeria*',
            'Pengaturan ulang kata sandi',
            '',
            "Halo {$user->name}, buka tautan berikut untuk membuat kata sandi baru:",
            $url,
            '',
            "Tautan hanya dapat digunakan satu kali dan berlaku selama {$minutes} menit.",
            'Abaikan pesan ini jika Anda tidak meminta pengaturan ulang.',
        ]);

        $this->queue(
            'password-reset:'.$user->id.':'.hash('sha256', $url),
            $user->id,
            'account',
            $user->id,
            'password_reset',
            'account',
            $user->name,
            $user->phone,
            $message,
            0,
        );
    }

    public function loading(LoadingPermit $permit, string $event, string $title, string $body): void
    {
        $setting = WhatsappSetting::current();
        if (! $setting?->is_active) {
            return;
        }

        $this->dispatchApplicant(
            $setting,
            'loading',
            $permit->id,
            $event,
            $permit->applicant_name,
            $permit->applicant_phone,
            $this->message($title, $permit->permit_number, $permit->status_label, $body, ApplicantStatusUrl::loading($permit)),
        );

        $staffMessage = $this->message($title, $permit->permit_number, $permit->status_label, $body, null);
        $this->loadingRecipients()->each(fn (User $user, int $index) => $this->dispatchStaff(
            $user,
            'loading',
            $permit->id,
            $event,
            $staffMessage,
            $index,
        ));
    }

    public function work(WorkPermit $permit, string $event, string $title, string $body): void
    {
        $setting = WhatsappSetting::current();
        if (! $setting?->is_active) {
            return;
        }

        $this->dispatchApplicant(
            $setting,
            'work',
            $permit->id,
            $event,
            $permit->applicant_name,
            $permit->applicant_phone,
            $this->message($title, $permit->permit_number, $permit->status_label, $body, ApplicantStatusUrl::work($permit)),
        );

        $staffMessage = $this->message($title, $permit->permit_number, $permit->status_label, $body, route('staff.work-permits.show', $permit->public_token));
        $this->workRecipients($permit)->each(fn (User $user, int $index) => $this->dispatchStaff(
            $user,
            'work',
            $permit->id,
            $event,
            $staffMessage,
            $index,
        ));
    }

    private function dispatchApplicant(
        WhatsappSetting $setting,
        string $permitType,
        int $permitId,
        string $event,
        string $name,
        ?string $phone,
        string $message,
    ): void {
        if (! $setting->notify_applicants || ! IndonesianPhoneNumber::normalize($phone)) {
            return;
        }

        $this->queue(
            "{$permitType}:{$permitId}:{$event}:applicant",
            null,
            $permitType,
            $permitId,
            $event,
            'applicant',
            $name,
            $phone,
            $message,
            0,
        );
    }

    private function dispatchStaff(User $user, string $permitType, int $permitId, string $event, string $message, int $index): void
    {
        if (! IndonesianPhoneNumber::normalize($user->phone)) {
            return;
        }

        $this->queue(
            "{$permitType}:{$permitId}:{$event}:staff:{$user->id}",
            $user->id,
            $permitType,
            $permitId,
            $event,
            'staff',
            $user->name,
            $user->phone,
            $message,
            ($index + 1) * 3,
        );
    }

    private function queue(
        string $uniqueKey,
        ?int $userId,
        string $permitType,
        int $permitId,
        string $event,
        string $recipientKind,
        ?string $recipientName,
        string $phone,
        string $message,
        int $delaySeconds,
    ): void {
        try {
            $log = WhatsappMessageLog::firstOrCreate(
                ['unique_key' => hash('sha256', $uniqueKey)],
                [
                    'user_id' => $userId,
                    'permit_type' => $permitType,
                    'permit_id' => $permitId,
                    'event' => $event,
                    'recipient_kind' => $recipientKind,
                    'recipient_name' => $recipientName,
                    'recipient_phone' => IndonesianPhoneNumber::normalize($phone),
                    'recipient_phone_hash' => hash('sha256', (string) IndonesianPhoneNumber::normalize($phone)),
                    'message' => $message,
                    'status' => 'queued',
                ],
            );
        } catch (UniqueConstraintViolationException) {
            return;
        }

        if (! $log->wasRecentlyCreated) {
            return;
        }

        SendWhatsappMessage::dispatch($log->id)
            ->delay(now()->addSeconds($delaySeconds))
            ->afterCommit();
    }

    private function loadingRecipients(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where('whatsapp_notifications_enabled', true)
            ->where(function ($query): void {
                $query->whereIn('role', ['admin', 'secretary'])
                    ->orWhere(function ($validator): void {
                        $validator->where('role', 'validator')->where('division', 'TR');
                    });
            })
            ->orderBy('id')
            ->get();
    }

    private function workRecipients(WorkPermit $permit): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where('whatsapp_notifications_enabled', true)
            ->where(function ($query) use ($permit): void {
                $query->whereIn('role', ['admin', 'secretary'])
                    ->orWhere(function ($validator) use ($permit): void {
                        $validator->where('role', 'validator')->where('division', $permit->assigned_division);
                    });

                if ($permit->assigned_division === 'MEP' && $permit->deposit_required) {
                    $query->orWhere(function ($finance): void {
                        $finance->where('role', 'validator')->where('division', 'FIN');
                    });
                }
            })
            ->orderBy('id')
            ->get();
    }

    private function message(string $title, string $permitNumber, string $status, string $body, ?string $url): string
    {
        $clean = fn (string $value): string => trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));
        $lines = [
            '*Mal Bali Galeria*',
            $clean($title),
            '',
            'Nomor: '.$clean($permitNumber),
            'Status: '.$clean($status),
            $clean($body),
        ];

        if ($url) {
            $lines[] = '';
            $lines[] = 'Buka detail: '.$url;
        }

        $lines[] = '';
        $lines[] = 'Pesan otomatis. Jangan membalas pesan ini.';

        return implode("\n", $lines);
    }
}
