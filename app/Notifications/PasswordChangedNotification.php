<?php

namespace App\Notifications;

use App\Services\MailSettingsConfigurator;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return filled($notifiable->email) ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(MailSettingsConfigurator::class)->apply(true);
        $message = (new MailMessage)
            ->subject('[MBG] Kata Sandi Akun Telah Diubah')
            ->greeting("Halo {$notifiable->name},")
            ->line('Kata sandi akun Portal Perizinan Mal Bali Galeria baru saja diubah.')
            ->line('Seluruh sesi dan perangkat terpercaya sebelumnya telah dicabut.')
            ->action('Masuk ke Portal', route('login'))
            ->line('Segera hubungi administrator apabila Anda tidak melakukan perubahan ini.');

        MailBranding::embedLogo($message);

        return $message->salutation('Mal Bali Galeria - Property Management');
    }
}
