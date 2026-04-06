<?php

namespace App\Actions\Expenses;

use App\Models\Record;
use App\Models\User;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

class AddRecord
{
    public function handle(User $user, array $data): Record
    {
        return DB::transaction(function () use ($user, $data) {

            // Get or create the current expense month
            $expense = Expense::firstOrCreate(
                [
                    'house_id' => $user->house_id,
                    'month' => $data['month'] ?? now()->format('Y-m'),
                ]
            );

            // Resolve included mates with their names
            $includedMates = [];
            if (!empty($data['included_mates'])) {
                $mates = User::whereIn('id', $data['included_mates'])->get(['id', 'name']);
                foreach ($mates as $mate) {
                    $includedMates[] = [
                        'id' => $mate->id,
                        'name' => $mate->name,
                    ];
                }
            }

            // Get paid_by user
            $paidByUser = User::find($data['paid_by']);

            // Create the record
            $record = Record::create([
                'expense_id' => $expense->id,
                'added_by' => $user->id,
                'added_by_name' => $user->name, // store name
                'description' => $data['description'],
                'amount' => $data['amount'],
                'category_id' => $data['category_id'] ?? null,
                'included_mates' => $includedMates, // store id + name
                'paid_by' => $data['paid_by'],
                'paid_by_name' => $paidByUser?->name ?? 'Unknown',
                'timestamp' => now(),
            ]);

            return $record->load('category'); // eager load category
        });
    }
}