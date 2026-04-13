<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HouseWallPollVoted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $houseId,
        public readonly int $postId,
        public readonly array $counts, // optionId => votes
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-house-wall.' . $this->houseId)];
    }

    public function broadcastAs(): string
    {
        return 'wall.poll.voted';
    }

    public function broadcastWith(): array
    {
        return [
            'postId' => $this->postId,
            'counts' => $this->counts,
        ];
    }
}

