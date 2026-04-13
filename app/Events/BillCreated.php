<?php

namespace App\Events;

use App\Models\Record;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $houseId,
        public readonly Record $record,
        /** @var array<int, float> userId => share */
        public readonly array $shares,
        public readonly string $currency,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-house.' . $this->houseId)];
    }

    public function broadcastAs(): string
    {
        return 'bill.created';
    }

    public function broadcastWith(): array
    {
        return [
            'billId' => $this->record->id,
            'billName' => $this->record->description,
            'amount' => (float) $this->record->amount,
            'currency' => $this->currency,
            'paidByUserId' => (int) $this->record->paid_by,
            'paidByName' => $this->record->paid_by_name,
            'addedByUserId' => (int) $this->record->added_by,
            'addedByName' => $this->record->added_by_name,
            'month' => $this->record->expense?->month,
            'shares' => $this->shares, // userId => share
        ];
    }
}

