<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TripExpenseShareCalculator
{
    /**
     * @param  Collection<int, array{user_id:int,value?:float}>  $participants
     * @return array<int, float> [userId => shareAmount]
     */
    public function compute(string $splitMethod, float $amount, Collection $participants): array
    {
        $ordered = ExpenseSplit::sortIncludedMates(
            $participants->map(fn (array $p) => ['id' => $p['user_id']])->all()
        );

        return match ($splitMethod) {
            'equal' => ExpenseSplit::sharePerUser($amount, $ordered),
            'percentage' => $this->percentageShares($amount, $participants, $ordered),
            'exact' => $this->exactShares($amount, $participants),
            default => throw ValidationException::withMessages([
                'split_method' => 'Unsupported split method.',
            ]),
        };
    }

    private function percentageShares(float $amount, Collection $participants, array $ordered): array
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

    private function exactShares(float $amount, Collection $participants): array
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
