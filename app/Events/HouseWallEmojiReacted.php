<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HouseWallEmojiReacted implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $houseId,
        public readonly int $postId,
        public readonly array $emojiCounts, // emoji => count
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-house-wall.' . $this->houseId)];
    }

    public function broadcastAs(): string
    {
        return 'wall.emoji.reacted';
    }

    public function broadcastWith(): array
    {
        return [
            'postId' => $this->postId,
            'emoji_counts' => $this->emojiCounts,
        ];
    }
}

