<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripSettlement;
use App\Models\User;
use App\Services\SettlementEngine;
use Illuminate\Support\Facades\DB;

class TripSettlementService
{
    /**
     * Apply completed (paid) transfers to net balances from trip expenses.
     *
     * Convention matches SettlementEngine: positive = net creditor, negative = net debtor.
     * A paid settlement from A → B moves money so A owes less and B is owed less.
     */
    public function applyPaidSettlementsToNetBalances(int $tripId, array $balance): array
    {
        $paid = TripSettlement::where('trip_id', $tripId)
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

    public function generate($tripId): array
    {
        $trip = Trip::findOrFail($tripId);

        $paid = $trip->expenses()->selectRaw('paid_by, SUM(amount) as total')
            ->groupBy('paid_by')->pluck('total', 'paid_by');

        $owed = DB::table('trip_expense_user')
            ->join('trip_expenses', 'trip_expenses.id', '=', 'trip_expense_user.trip_expense_id')
            ->where('trip_expenses.trip_id', $trip->id)
            ->selectRaw('trip_expense_user.user_id, SUM(trip_expense_user.share_amount) as total')
            ->groupBy('trip_expense_user.user_id')
            ->pluck('total', 'user_id');

        $userIds = collect($paid->keys())->merge($owed->keys())->unique();
        $balances = $userIds->mapWithKeys(function ($userId) use ($paid, $owed) {
            $net = (float) ($paid[$userId] ?? 0) - (float) ($owed[$userId] ?? 0);
            return [$userId => $net];
        })->all();

        $balances = $this->applyPaidSettlementsToNetBalances($tripId, $balances);

        $engine = new SettlementEngine();
        $transactions = $engine->optimize($balances);

        // Replace pending suggestions only — paid settlements are historical truth and are never deleted here.
        TripSettlement::where('trip_id', $tripId)
            ->where('status', 'pending')
            ->where('source', 'engine')
            ->delete();

        foreach ($transactions as $tx) {
            $fromUser = User::withTrashed()->find($tx['from_user_id']);
            $toUser = User::withTrashed()->find($tx['to_user_id']);

            TripSettlement::create([
                'trip_id' => $tripId,
                'from_user_id' => $tx['from_user_id'],
                'to_user_id' => $tx['to_user_id'],
                'from_name' => $fromUser?->name ?? 'Unknown',
                'to_name' => $toUser?->name ?? 'Unknown',
                'amount' => $tx['amount'],
                'source' => 'engine',
                'type' => 'expense',
                'status' => 'pending',
            ]);
        }

        return [
            'transactions' => $transactions,
            'net_balances' => $balances,
            'record_count' => $trip->expenses()->count(),
        ];
    }
}
