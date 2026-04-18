<?php

namespace App\Actions\Expenses;

use App\Models\Record;
use App\Models\User;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateRecord
{
    public function handle(User $user, Record $record, array $data): Record
    {
        return DB::transaction(function () use ($user, $record, $data) {

            // ✅ 1. Resolve month (from request OR existing expense)
            $month = $data['month']
                ?? optional($record->expense)->month
                ?? now()->format('Y-m');

            // ✅ 2. Get or create expense (month-based)
            $expense = Expense::firstOrCreate([
                'house_id' => $user->house_id,
                'month' => $month,
            ]);

            // ✅ 3. Resolve included mates with names (same as AddRecord)
            $includedMates = [];

            if (!empty($data['included_mates'])) {
                $mates = User::whereIn('id', $data['included_mates'])
                    ->get(['id', 'name']);

                foreach ($mates as $mate) {
                    $includedMates[] = [
                        'id' => $mate->id,
                        'name' => $mate->name,
                    ];
                }
            } else {
                // fallback to existing
                $includedMates = $record->included_mates ?? [];
            }

            // ✅ 4. Resolve paid_by user
            $paidByUser = isset($data['paid_by'])
                ? User::find($data['paid_by'])
                : null;

            $splitMethod = $data['split_method'] ?? ($record->split_method ?? 'equal');
            $excludedByUser = is_array($data['excluded_days_by_user'] ?? null)
                ? $data['excluded_days_by_user']
                : null;
            $guestExtraByUser = is_array($data['guest_extra_days_by_user'] ?? null)
                ? $data['guest_extra_days_by_user']
                : null;

            $billPeriodDays = $record->bill_period_days;
            if ($splitMethod === 'days') {
                try {
                    $billPeriodDays = Carbon::createFromFormat('Y-m', $month)->daysInMonth;
                } catch (\Throwable) {
                    // keep existing
                }
            } else {
                $billPeriodDays = null;
            }

            // ✅ 5. Update record (same structure as AddRecord)
            $record->update([
                'expense_id' => $expense->id,

                'added_by' => $user->id,
                'added_by_name' => $user->name,

                'description' => $data['description'] ?? $record->description,
                'amount' => $data['amount'] ?? $record->amount,
                'category_id' => $data['category_id'] ?? $record->category_id,

                'included_mates' => $includedMates,

                'paid_by' => $data['paid_by'] ?? $record->paid_by,
                'paid_by_name' => $paidByUser?->name
                    ?? $record->paid_by_name
                    ?? 'Unknown',

                'split_method' => $splitMethod,
                'bill_period_days' => $billPeriodDays,

                // ⚠️ Keep or update depending on your design
                'timestamp' => now(),
            ]);

            // Upsert per-mate split rows when either map is provided.
            if (is_array($excludedByUser) || is_array($guestExtraByUser)) {
                try {
                    $prevEx = $record->excluded_days_by_user;
                    $prevGx = $record->guest_extra_days_by_user;
                    $rows = [];
                    foreach ($includedMates as $mate) {
                        $uid = (int) ($mate['id'] ?? 0);
                        if (!$uid) continue;
                        $ex = is_array($excludedByUser)
                            ? (int) ($excludedByUser[$uid] ?? $excludedByUser[(string) $uid] ?? 0)
                            : (int) ($prevEx[$uid] ?? 0);
                        if ($ex < 0) $ex = 0;
                        $gx = is_array($guestExtraByUser)
                            ? (int) ($guestExtraByUser[$uid] ?? $guestExtraByUser[(string) $uid] ?? 0)
                            : (int) ($prevGx[$uid] ?? 0);
                        if ($gx < 0) $gx = 0;
                        $rows[] = [
                            'record_id' => (int) $record->id,
                            'user_id' => $uid,
                            'excluded_days' => $ex,
                            'guest_extra_days' => $gx,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    if (!empty($rows)) {
                        DB::table('record_user')->upsert(
                            $rows,
                            ['record_id', 'user_id'],
                            ['excluded_days', 'guest_extra_days', 'updated_at']
                        );
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }

            return $record->load('category');
        });
    }
}