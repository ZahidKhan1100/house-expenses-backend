<?php

namespace App\Services;

class SettlementEngine
{
    /**
     * Build optimal settlement transactions (min transfers) in whole cents.
     * Each transfer amount is exact cents; debtor/creditor pools drain to zero.
     */
    public function optimize(array $balances): array
    {
        $creditors = [];
        $debtors = [];

        foreach ($balances as $userId => $amount) {
            $cents = (int) round(((float) $amount) * 100);
            if ($cents === 0) {
                continue;
            }
            if ($cents > 0) {
                $creditors[] = ['id' => (int) $userId, 'cents' => $cents];
            } else {
                $debtors[] = ['id' => (int) $userId, 'cents' => -$cents];
            }
        }

        usort($creditors, fn ($a, $b) => $b['cents'] <=> $a['cents']);
        usort($debtors, fn ($a, $b) => $b['cents'] <=> $a['cents']);

        $this->rebalancePools($creditors, $debtors);

        $transactions = [];
        $i = 0;
        $j = 0;

        while ($i < count($debtors) && $j < count($creditors)) {
            $debt = &$debtors[$i];
            $credit = &$creditors[$j];

            $payCents = min($debt['cents'], $credit['cents']);
            if ($payCents > 0) {
                $transactions[] = [
                    'from_user_id' => $debt['id'],
                    'to_user_id' => $credit['id'],
                    'amount' => $payCents / 100.0,
                ];
            }

            $debt['cents'] -= $payCents;
            $credit['cents'] -= $payCents;

            if ($debt['cents'] <= 0) {
                $i++;
            }
            if ($credit['cents'] <= 0) {
                $j++;
            }
        }

        return $transactions;
    }

    /**
     * Fix sub-cent drift from rounded net balances so total credits == total debts.
     *
     * @param  list<array{id: int, cents: int}>  $creditors
     * @param  list<array{id: int, cents: int}>  $debtors
     */
    private function rebalancePools(array &$creditors, array &$debtors): void
    {
        $creditSum = array_sum(array_column($creditors, 'cents'));
        $debtSum = array_sum(array_column($debtors, 'cents'));
        $diff = $creditSum - $debtSum;

        if ($diff === 0) {
            return;
        }

        if ($diff > 0 && $creditors !== []) {
            $creditors[0]['cents'] -= $diff;
        } elseif ($diff < 0 && $debtors !== []) {
            $debtors[0]['cents'] += $diff;
        }

        $creditors = array_values(array_filter($creditors, fn ($c) => $c['cents'] > 0));
        $debtors = array_values(array_filter($debtors, fn ($d) => $d['cents'] > 0));
    }
}
