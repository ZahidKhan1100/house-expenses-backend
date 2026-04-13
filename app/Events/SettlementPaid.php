<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettlementPaid implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $toUserId,
        public readonly int $fromUserId,
        public readonly string $fromName,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $month,
        public readonly int $settlementId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-user.' . $this->toUserId)];
    }

    public function broadcastAs(): string
    {
        return 'settlement.paid';
    }

    public function broadcastWith(): array
    {
        return [
            'settlementId' => $this->settlementId,
            'toUserId' => $this->toUserId,
            'fromUserId' => $this->fromUserId,
            'fromName' => $this->fromName,
            'amount' => $this->amount,
            'amountFormatted' => $this->currency . number_format($this->amount, 2),
            'currency' => $this->currency,
            'month' => $this->month,
        ];
    }
}

