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
    /**
     * Apply completed (paid) transfers for the month to net balances from expenses.
     *
     * Convention matches BalanceCalculator / PaymentController: positive = net creditor,
     * negative = net debtor. A paid settlement from A → B moves money so A owes less and
     * B is owed less.
     */
    public function applyPaidSettlementsToNetBalances(int $houseId, string $month, array $balance): array
    {
        // Only apply paid transfers that relate to expense balancing.
        // Manual transfers like stock buy-backs are separate “ledgers” and should NOT
        // reduce (or invert) expense-based balances when regenerating settlement plans.
        $paid = Settlement::where('house_id', $houseId)
            ->where('month', $month)
            ->where('status', 'paid')
            ->where(function ($q) {
                $q->whereNull('type')->orWhere('type', 'expense');
            })
            ->get();

        $balanceCents = [];
        foreach ($balance as $id => $v) {
            $balanceCents[(int) $id] = (int) round(((float) $v) * 100);
        }

        foreach ($paid as $s) {
            $from = (int) $s->from_user_id;
            $to = (int) $s->to_user_id;
            if (! array_key_exists($from, $balanceCents)) {
                $balanceCents[$from] = 0;
            }
            if (! array_key_exists($to, $balanceCents)) {
                $balanceCents[$to] = 0;
            }
            $amtCents = (int) round(((float) $s->amount) * 100);
            $balanceCents[$from] += $amtCents;
            $balanceCents[$to] -= $amtCents;
        }

        $balance = [];
        foreach ($balanceCents as $id => $cents) {
            $dollars = $cents / 100.0;
            $balance[$id] = abs($dollars) < 0.005 ? 0.0 : round($dollars, 2);
        }

        return $balance;
    }

    public function generate($houseId, $month)
    {
        $expense = Expense::where('house_id', $houseId)
            ->where('month', $month)
            ->first();

        \Log::info('Settlement generate expense lookup', [
            'house_id' => $houseId,
            'month' => $month,
            'expense_id' => $expense?->id,
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

        $balances = $balanceCalculator->calculateWithCache((int) $houseId, (string) $month, $records, $mateIds);

        $balances = $this->applyPaidSettlementsToNetBalances($houseId, $month, $balances);

        $transactions = $engine->optimize($balances);

        \Log::info('Settlement generate balances', [
            'house_id' => $houseId,
            'month' => $month,
            'record_count' => $records->count(),
            'balances' => $balances,
            'transaction_count' => count($transactions),
        ]);

        // Replace pending suggestions only — paid settlements are historical truth and are never deleted here.
        Settlement::where('house_id', $houseId)
            ->where('month', $month)
            ->where('status', 'pending')
            ->where('source', 'engine')
            ->delete();


        // store new settlements
        foreach ($transactions as $tx) {

            $fromUser = User::withTrashed()->find($tx['from_user_id']);
            $toUser = User::withTrashed()->find($tx['to_user_id']);
            Settlement::create([
                'house_id' => $houseId,
                'month' => $month,
                'from_user_id' => $tx['from_user_id'],
                'to_user_id' => $tx['to_user_id'],
                'from_name' => $fromUser?->name ?? 'Unknown',
                'to_name' => $toUser?->name ?? 'Unknown',
                'amount' => $tx['amount'],
                'source' => 'engine',
                'type' => 'expense',
                'status' => 'pending', // 🔥 IMPORTANT
            ]);
        }

        return [
            'transactions' => $transactions,
            'net_balances' => $balances,
            'record_count' => $records->count(),
        ];
    }
}