<?php

namespace App\Events;

use App\Models\HouseWallPost;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HouseWallPostCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $houseId,
        public readonly HouseWallPost $post,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-house-wall.' . $this->houseId)];
    }

    public function broadcastAs(): string
    {
        return 'wall.posted';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

