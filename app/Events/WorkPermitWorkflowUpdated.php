<?php

namespace App\Events;

use App\Models\PermitNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkPermitWorkflowUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly PermitNotification $notification,
        public readonly string $category,
        public readonly int $pendingCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("validators.{$this->notification->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'work-permit.workflow-updated';
    }

    public function broadcastWith(): array
    {
        $user = $this->notification->user;
        $readRoute = match (true) {
            $user?->isAdmin() => 'admin.notifications.read',
            $user?->division === 'FIN' => 'finance.notifications.read',
            $user?->division === 'TR' => 'tr.notifications.read',
            default => 'mep.notifications.read',
        };

        return [
            'category' => $this->category,
            'notification' => [
                'id' => $this->notification->id,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'read_url' => route($readRoute, $this->notification),
            ],
            'pending_count' => $this->pendingCount,
        ];
    }
}
