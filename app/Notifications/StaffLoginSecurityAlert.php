<?php

namespace App\Notifications;

use App\Models\StaffLoginEvent;
use App\Services\MailSettingsConfigurator;
use App\Support\MailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class StaffLoginSecurityAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $eventId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        $channels = [WebPushChannel::class];

        if (filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $event = $this->event();

        return (new WebPushMessage)
            ->title($event->title())
            ->body($this->summary($event))
            ->icon(asset('pwa/icon-192.png'))
            ->badge(asset('pwa/badge-96.png'))
            ->lang('id')
            ->tag("staff-login-security-{$event->id}")
            ->renotify()
            ->vibrate([180, 80, 180])
            ->data(['url' => $this->url($notifiable, $event)])
            ->options(['TTL' => 3600, 'urgency' => 'high']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        app(MailSettingsConfigurator::class)->apply(true);
        $event = $this->event();

        $message = (new MailMessage)
            ->subject('[MBG] '.$event->title())
            ->greeting("Halo {$notifiable->name},")
            ->line($this->summary($event))
            ->line("Akun: {$event->user->name}")
            ->line("Perangkat: {$event->device_label} · {$event->browser_label}")
            ->line('Waktu: '.$event->occurred_at->translatedFormat('d F Y, H:i').' WITA')
            ->line('Alamat IP: '.($event->ip_address ?: 'Tidak tersedia'));

        MailBranding::embedLogo($message);

        return $message
            ->action('Periksa Keamanan Akun', $this->url($notifiable, $event))
            ->line('Jika aktivitas ini tidak dikenali, segera ganti kata sandi dan cabut sesi perangkat lain.')
            ->salutation('Mal Bali Galeria - Property Management');
    }

    private function event(): StaffLoginEvent
    {
        return StaffLoginEvent::query()->with('user')->findOrFail($this->eventId);
    }

    private function summary(StaffLoginEvent $event): string
    {
        return $event->event === StaffLoginEvent::FAILED_ATTEMPTS
            ? "Terdeteksi {$event->attempt_count} percobaan login gagal untuk akun {$event->user->name}."
            : "Akun {$event->user->name} baru saja digunakan dari perangkat yang belum dikenali.";
    }

    private function url(object $notifiable, StaffLoginEvent $event): string
    {
        return $notifiable->isAdmin() && $notifiable->id !== $event->user_id
            ? route('admin.settings.security.edit').'#riwayat-login-staf'
            : route('account.security.index').'#riwayat-login';
    }
}
