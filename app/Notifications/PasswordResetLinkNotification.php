<?php

namespace App\Notifications;

use App\Services\MailSettingsConfigurator;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLinkNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $url)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(MailSettingsConfigurator::class)->apply(true);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $message = (new MailMessage)
            ->subject('[MBG] Pengaturan Ulang Kata Sandi')
            ->greeting("Halo {$notifiable->name},")
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun Portal Perizinan Mal Bali Galeria.')
            ->action('Atur Ulang Kata Sandi', $this->url)
            ->line("Tautan ini hanya dapat digunakan satu kali dan berlaku selama {$minutes} menit.")
            ->line('Abaikan pesan ini jika Anda tidak meminta pengaturan ulang kata sandi.');

        MailBranding::embedLogo($message);

        return $message->salutation('Mal Bali Galeria - Property Management');
    }
}
