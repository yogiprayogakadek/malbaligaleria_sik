<?php

namespace App\Notifications;

use App\Models\WorkPermit;
use App\Services\MailSettingsConfigurator;
use App\Support\ApplicantStatusUrl;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkPermitApplicantMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkPermit $permit,
        public string $messageTitle,
        public string $messageBody,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(MailSettingsConfigurator::class)->apply(true);

        $message = (new MailMessage)
            ->subject("[MBG] {$this->messageTitle} - {$this->permit->permit_number}")
            ->greeting("Halo {$this->permit->applicant_name},")
            ->line($this->messageBody)
            ->line("Nomor permohonan: {$this->permit->permit_number}")
            ->line("Status: {$this->permit->status_label}");

        if ($this->permit->deposit_required && $this->permit->deposit_amount) {
            $message->line('Nominal security deposit: Rp '.number_format((float) $this->permit->deposit_amount, 0, ',', '.'));
        }

        MailBranding::embedLogo($message);
        $expiryDays = (int) config('permit-notifications.status_link_expiry_days', 30);

        return $message
            ->action('Buka Status Permohonan', ApplicantStatusUrl::work($this->permit))
            ->line("Tautan berlaku selama {$expiryDays} hari dan bersifat pribadi. Jangan membagikannya kepada pihak lain.")
            ->salutation('Mal Bali Galeria - Property Management');
    }
}
