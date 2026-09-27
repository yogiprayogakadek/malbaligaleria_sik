<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ValidatorAccountDeactivated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly User $validator) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("validators.{$this->validator->id}")];
    }

    public function broadcastAs(): string
    {
        return 'validator-account.deactivated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => 'Akun Anda telah dinonaktifkan oleh administrator.',
        ];
    }
}
