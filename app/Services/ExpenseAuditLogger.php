<?php

namespace App\Services;

use App\Models\ExpenseAuditLog;
use App\Models\Record;
use App\Models\User;

class ExpenseAuditLogger
{
    public static function created(User $actor, Record $record): void
    {
        $desc = self::shortDescription($record);
        $amount = round((float) $record->amount, 2);

        ExpenseAuditLog::query()->create([
            'house_id' => (int) $actor->house_id,
            'expense_id' => (int) $record->expense_id,
            'record_id' => (int) $record->id,
            'actor_user_id' => (int) $actor->id,
            'action' => 'created',
            'summary' => 'Added ' . $desc . ' (' . self::moneyLabel($amount) . ')',
            'payload' => self::snapshot($record),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     */
    public static function updated(User $actor, Record $record, array $before): void
    {
        $desc = self::shortDescription($record);
        $amount = round((float) $record->amount, 2);

        ExpenseAuditLog::query()->create([
            'house_id' => (int) $actor->house_id,
            'expense_id' => (int) $record->expense_id,
            'record_id' => (int) $record->id,
            'actor_user_id' => (int) $actor->id,
            'action' => 'updated',
            'summary' => 'Updated ' . $desc . ' (' . self::moneyLabel($amount) . ')',
            'payload' => [
                'before' => $before,
                'after' => self::snapshot($record),
            ],
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function deleted(User $actor, array $snapshot): void
    {
        $desc = self::shortDescriptionFromSnapshot($snapshot);
        $amount = isset($snapshot['amount']) ? round((float) $snapshot['amount'], 2) : 0.0;

        ExpenseAuditLog::query()->create([
            'house_id' => (int) $actor->house_id,
            'expense_id' => isset($snapshot['expense_id']) ? (int) $snapshot['expense_id'] : null,
            'record_id' => isset($snapshot['id']) ? (int) $snapshot['id'] : null,
            'actor_user_id' => (int) $actor->id,
            'action' => 'deleted',
            'summary' => 'Removed ' . $desc . ' (' . self::moneyLabel($amount) . ')',
            'payload' => ['deleted' => $snapshot],
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Record $record): array
    {
        return [
            'id' => (int) $record->id,
            'expense_id' => (int) $record->expense_id,
            'description' => $record->description,
            'amount' => round((float) $record->amount, 2),
            'category_id' => $record->category_id,
            'paid_by' => $record->paid_by,
            'paid_by_name' => $record->paid_by_name,
            'split_method' => $record->split_method,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshotForDelete(Record $record): array
    {
        return self::snapshot($record);
    }

    private static function shortDescription(Record $record): string
    {
        $t = trim((string) ($record->description ?? ''));
        if ($t === '') {
            return 'an expense';
        }
        if (strlen($t) > 80) {
            return '"' . substr($t, 0, 77) . '…' . '"';
        }

        return '"' . $t . '"';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function shortDescriptionFromSnapshot(array $snapshot): string
    {
        $t = trim((string) ($snapshot['description'] ?? ''));
        if ($t === '') {
            return 'an expense';
        }
        if (strlen($t) > 80) {
            return '"' . substr($t, 0, 77) . '…' . '"';
        }

        return '"' . $t . '"';
    }

    private static function moneyLabel(float $amount): string
    {
        return '$' . number_format($amount, 2);
    }
}
