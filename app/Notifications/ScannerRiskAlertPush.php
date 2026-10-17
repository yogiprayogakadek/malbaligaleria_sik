<?php

namespace App\Notifications;

use App\Models\ScannerIpBlock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class ScannerRiskAlertPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $blockId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $block = ScannerIpBlock::findOrFail($this->blockId);

        return (new WebPushMessage)
            ->title('Aktivitas scanner berisiko')
            ->body("Terdeteksi {$block->trigger_count} verifikasi berisiko dari satu sumber.")
            ->icon(asset('pwa/icon-192.png'))
            ->badge(asset('pwa/badge-96.png'))
            ->lang('id')
            ->tag('scanner-risk-'.$block->id)
            ->renotify()
            ->vibrate([180, 80, 180])
            ->data(['url' => route('admin.scanner-logs.index')])
            ->options(['TTL' => 1800, 'urgency' => 'high']);
    }
}
