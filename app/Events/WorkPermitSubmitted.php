<?php

namespace App\Events;

use App\Models\PermitNotification;
use App\Models\WorkPermit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkPermitSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PermitNotification $notification,
        public readonly WorkPermit $permit,
        public readonly int $pendingCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("validators.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'work-permit.submitted';
    }

    public function broadcastWith(): array
    {
        $readRoute = match (true) {
            $this->notification->user?->isAdmin() => 'admin.notifications.read',
            $this->notification->user?->isSecretary() => 'secretary.notifications.read',
            $this->notification->user?->division === 'TR' => 'tr.notifications.read',
            default => 'mep.notifications.read',
        };

        return [
            'category' => 'work',
            'notification' => [
                'id' => $this->notification->id,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'read_url' => route($readRoute, $this->notification),
            ],
            'permit' => [
                'number' => $this->permit->permit_number,
                'contractor_name' => $this->permit->contractor_name,
            ],
            'pending_count' => $this->pendingCount,
        ];
    }
}
