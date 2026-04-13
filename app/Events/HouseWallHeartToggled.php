<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HouseWallHeartToggled implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $houseId,
        public readonly int $postId,
        public readonly int $heartsCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-house-wall.' . $this->houseId)];
    }

    public function broadcastAs(): string
    {
        return 'wall.heart.toggled';
    }

    public function broadcastWith(): array
    {
        return [
            'postId' => $this->postId,
            'heartsCount' => $this->heartsCount,
        ];
    }
}

