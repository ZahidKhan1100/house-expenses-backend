<?php

namespace App\Services;

class SettlementEngine
{
    /**
     * Build optimal settlement transactions (min transfers)
     */
    public function optimize(array $balances): array
    {
        $creditors = [];
        $debtors = [];

        foreach ($balances as $userId => $amount) {
            if ($amount > 0) {
                $creditors[] = ['id' => $userId, 'amount' => $amount];
            } elseif ($amount < 0) {
                $debtors[] = ['id' => $userId, 'amount' => abs($amount)];
            }
        }

        usort($creditors, fn($a, $b) => $b['amount'] <=> $a['amount']);
        usort($debtors, fn($a, $b) => $b['amount'] <=> $a['amount']);

        $transactions = [];

        $i = 0;
        $j = 0;

        while ($i < count($debtors) && $j < count($creditors)) {

            $debt = &$debtors[$i];
            $credit = &$creditors[$j];

            $amount = min($debt['amount'], $credit['amount']);
            $rounded = round($amount, 2);

            if ($rounded < 0.01) {
                $debt['amount'] -= $amount;
                $credit['amount'] -= $amount;
                if ($debt['amount'] < 0.005) {
                    $i++;
                }
                if ($credit['amount'] < 0.005) {
                    $j++;
                }
                continue;
            }

            $transactions[] = [
                'from_user_id' => $debt['id'],
                'to_user_id' => $credit['id'],
                'amount' => $rounded,
            ];

            $debt['amount'] -= $amount;
            $credit['amount'] -= $amount;

            if ($debt['amount'] < 0.005) {
                $i++;
            }
            if ($credit['amount'] < 0.005) {
                $j++;
            }
        }

        return $transactions;
    }
}