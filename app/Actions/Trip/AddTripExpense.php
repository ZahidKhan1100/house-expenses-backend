<?php

namespace App\Actions\Trip;

use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\User;
use App\Services\ExpenseSplit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddTripExpense
{
    /**
     * @param  array{title:string,amount:float,currency?:string,notes?:string,split_method:string,participants:array<int,array{user_id:int,value?:float}>}  $data
     */
    public function execute(Trip $trip, User $paidBy, array $data): TripExpense
    {
        $amount = (float) $data['amount'];
        $splitMethod = $data['split_method'] ?? 'equal';
        $participants = collect($data['participants'] ?? []);

        $memberIds = $trip->members()->pluck('users.id')->all();
        $invalidIds = $participants->pluck('user_id')->diff($memberIds);
        if ($invalidIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'participants' => 'All participants must be members of this trip.',
            ]);
        }

        $ordered = ExpenseSplit::sortIncludedMates(
            $participants->map(fn (array $p) => ['id' => $p['user_id']])->all()
        );

        $shares = match ($splitMethod) {
            'equal' => ExpenseSplit::sharePerUser($amount, $ordered),
            'percentage' => $this->percentageShares($amount, $participants, $ordered),
            'exact' => $this->exactShares($amount, $participants),
            default => throw ValidationException::withMessages([
                'split_method' => 'Unsupported split method.',
            ]),
        };

        return DB::transaction(function () use ($trip, $paidBy, $data, $shares) {
            $expense = TripExpense::create([
                'trip_id' => $trip->id,
                'paid_by' => $paidBy->id,
                'title' => $data['title'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $trip->currency,
                'notes' => $data['notes'] ?? null,
                'split_method' => $data['split_method'] ?? 'equal',
            ]);

            $rows = [];
            foreach ($shares as $userId => $shareAmount) {
                $rows[] = [
                    'trip_expense_id' => $expense->id,
                    'user_id' => $userId,
                    'share_amount' => $shareAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('trip_expense_user')->insert($rows);

            return $expense->load('participants', 'payer');
        });
    }

    private function percentageShares(float $amount, $participants, array $ordered): array
    {
        $weighted = $ordered;
        foreach ($weighted as &$row) {
            $match = $participants->firstWhere('user_id', $row['id']);
            $row['weight'] = (float) ($match['value'] ?? 0);
        }
        unset($row);

        $totalPercent = collect($weighted)->sum('weight');
        if (abs($totalPercent - 100.0) > 0.01) {
            throw ValidationException::withMessages([
                'participants' => 'Percentages must sum to 100.',
            ]);
        }

        return ExpenseSplit::sharePerUserWeighted($amount, $weighted);
    }

    private function exactShares(float $amount, $participants): array
    {
        $shares = [];
        $sum = 0.0;
        foreach ($participants as $p) {
            $value = (float) ($p['value'] ?? 0);
            $shares[$p['user_id']] = $value;
            $sum += $value;
        }

        if (abs($sum - $amount) > 0.01) {
            throw ValidationException::withMessages([
                'participants' => 'Exact amounts must sum to the total expense amount.',
            ]);
        }

        return $shares;
    }
}
