<?php

namespace App\Notifications;

use App\Models\LoadingPermit;
use App\Services\MailSettingsConfigurator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

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
        $statusUrl = URL::temporarySignedRoute(
            'loading.show',
            now()->addDays($expiryDays),
            ['permitNumber' => $this->permit->permit_number],
        );

        $message = (new MailMessage)
            ->subject($this->subject())
            ->greeting("Halo {$this->permit->applicant_name},")
            ->line($this->openingLine())
            ->line("Nomor permohonan: {$this->permit->permit_number}")
            ->line("Tenant: {$this->permit->tenant_name}")
            ->line("Jenis pergerakan: {$this->permit->direction_label}")
            ->line('Periode: '.$this->permit->start_date->format('d M Y').' sampai '.$this->permit->end_date->format('d M Y'));

        if ($this->type === self::REJECTED && $this->permit->review_notes) {
            $message->line("Catatan validator: {$this->permit->review_notes}");
        }

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
