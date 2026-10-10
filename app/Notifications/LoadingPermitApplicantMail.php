<?php

namespace App\Notifications;

use App\Models\LoadingPermit;
use App\Services\MailSettingsConfigurator;
use App\Support\ApplicantStatusUrl;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoadingPermitApplicantMail extends Notification implements ShouldQueue
{
    use Queueable;

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public function __construct(
        public LoadingPermit $permit,
        public string $type,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(MailSettingsConfigurator::class)->apply(true);

        $expiryDays = (int) config('permit-notifications.status_link_expiry_days', 30);
        $statusUrl = ApplicantStatusUrl::loading($this->permit);

        $message = (new MailMessage)
            ->subject($this->subject())
            ->greeting("Halo {$this->permit->applicant_name},")
            ->line($this->openingLine())
            ->line("Nomor permohonan: {$this->permit->permit_number}")
            ->line("Tenant: {$this->permit->tenant_name}")
            ->line("Jenis pergerakan: {$this->permit->direction_label}")
            ->line('Periode: '.$this->permit->start_date->format('d M Y').' sampai '.$this->permit->end_date->format('d M Y'))
            ->line("{$this->permit->movement_time_field_label}: {$this->permit->movement_time_label}");

        if ($this->type === self::REJECTED && $this->permit->review_notes) {
            $message->line("Catatan validator: {$this->permit->review_notes}");
        }

        MailBranding::embedLogo($message);

        return $message
            ->action('Cek Status Permohonan', $statusUrl)
            ->line("Tautan status berlaku selama {$expiryDays} hari. Jangan membagikan tautan ini kepada pihak lain.")
            ->salutation('Mal Bali Galeria - Property Management');
    }

    private function subject(): string
    {
        $label = match ($this->type) {
            self::APPROVED => 'Permohonan Loading Disetujui',
            self::REJECTED => 'Perubahan Status Permohonan Loading',
            default => 'Permohonan Loading Diterima',
        };

        return "[MBG] {$label} - {$this->permit->permit_number}";
    }

    private function openingLine(): string
    {
        return match ($this->type) {
            self::APPROVED => 'Permohonan loading Anda telah disetujui oleh validator Tenant Relationship.',
            self::REJECTED => 'Permohonan loading Anda belum dapat disetujui oleh validator Tenant Relationship.',
            default => 'Permohonan loading Anda telah diterima dan masuk ke antrean verifikasi Tenant Relationship.',
        };
    }
}
