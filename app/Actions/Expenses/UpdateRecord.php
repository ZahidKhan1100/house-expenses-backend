<?php

namespace App\Actions\Expenses;

use App\Models\Record;
use App\Models\User;
use App\Models\Expense;
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

                // ⚠️ Keep or update depending on your design
                'timestamp' => now(),
            ]);

            return $record->load('category');
        });
    }
}