<?php

// app/Services/SettlementService.php

namespace App\Services;

use App\Models\Expense;
use App\Models\Record;
use App\Models\Settlement;
use App\Models\User;
use App\Services\BalanceCalculator;
use App\Services\SettlementEngine;

class SettlementService
{
    public function generate($houseId, $month)
    {
        $expense = Expense::where('house_id', $houseId)
            ->where('month', $month)
            ->first();

        \Log::info('Expense check', [
            'house_id' => $houseId,
            'month' => $month,
            'expense' => $expense
        ]);

        if (!$expense) {
            return [];
        }

        $records = Record::where('expense_id', $expense->id)->get();
        $mateIds = $records
            ->pluck('paid_by')
            ->merge($records->pluck('included_mates.*.id')->flatten())
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $balanceCalculator = new BalanceCalculator();
        $engine = new SettlementEngine();

        $balances = $balanceCalculator->calculate($records, $mateIds);

        $transactions = $engine->optimize($balances);

        // delete old settlements
        Settlement::where('house_id', $houseId)
            ->where('month', $month)
            ->delete();


        // store new settlements
        foreach ($transactions as $tx) {

            $fromUser = User::find($tx['from_user_id']);
            $toUser = User::find($tx['to_user_id']);
            Settlement::create([
                'house_id' => $houseId,
                'month' => $month,
                'from_user_id' => $tx['from_user_id'],
                'to_user_id' => $tx['to_user_id'],
                'from_name' => $fromUser?->name ?? 'Unknown',
                'to_name' => $toUser?->name ?? 'Unknown',
                'amount' => $tx['amount'],
            ]);
        }

        return $transactions;
    }
}