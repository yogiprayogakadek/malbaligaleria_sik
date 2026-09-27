<?php

namespace App\Events;

use App\Models\LoadingPermit;
use App\Models\PermitNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoadingPermitSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PermitNotification $notification,
        public readonly LoadingPermit $permit,
        public readonly int $pendingCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("validators.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'loading-permit.submitted';
    }

    public function broadcastWith(): array
    {
        $readRoute = $this->notification->user?->isAdmin()
            ? 'admin.notifications.read'
            : 'tr.notifications.read';

        return [
            'notification' => [
                'id' => $this->notification->id,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'read_url' => route($readRoute, $this->notification),
            ],
            'permit' => [
                'number' => $this->permit->permit_number,
                'tenant_name' => $this->permit->tenant_name,
                'direction' => $this->permit->direction_label,
            ],
            'pending_count' => $this->pendingCount,
        ];
    }
}
