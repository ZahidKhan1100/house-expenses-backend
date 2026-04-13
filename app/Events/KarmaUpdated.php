<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KarmaUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $delta,
        public readonly int $karmaBalance,
        public readonly int $level,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-user.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'karma.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'userId' => $this->userId,
            'delta' => $this->delta,
            'karma_balance' => $this->karmaBalance,
            'level' => $this->level,
            'reason' => $this->reason,
        ];
    }
}

