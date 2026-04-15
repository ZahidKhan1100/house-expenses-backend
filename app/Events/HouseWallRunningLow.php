<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HouseWallRunningLow implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $houseId,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-house-wall.' . $this->houseId)];
    }

    public function broadcastAs(): string
    {
        return 'wall.running_low';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
