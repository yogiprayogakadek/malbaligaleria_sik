<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormPresenceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $snapshot) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.form-presence')];
    }

    public function broadcastAs(): string
    {
        return 'form-presence.updated';
    }

    public function broadcastWith(): array
    {
        return $this->snapshot;
    }
}
