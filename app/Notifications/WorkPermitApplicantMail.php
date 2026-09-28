<?php

namespace App\Notifications;

use App\Models\WorkPermit;
use App\Services\MailSettingsConfigurator;
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

        return $message
            ->action('Buka Status Permohonan', route('work-permits.status', $this->permit->applicant_token))
            ->line('Tautan ini bersifat pribadi. Jangan membagikannya kepada pihak lain.')
            ->salutation('Mal Bali Galeria - Property Management');
    }
}
