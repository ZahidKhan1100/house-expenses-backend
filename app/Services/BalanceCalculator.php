<?php

// app/Services/BalanceCalculator.php

namespace App\Services;

use App\Models\Record;

class BalanceCalculator
{
    public function calculate($records, array $mateIds): array
    {
        $mateIds = array_map(static fn ($id) => (int) $id, $mateIds);
        $balance = array_fill_keys($mateIds, 0.0);

        foreach ($records as $rec) {

            $included = is_array($rec->included_mates)
                ? $rec->included_mates
                : [];

            // ensure payer included
            $exists = collect($included)->firstWhere('id', $rec->paid_by);

            if (!$exists) {
                $included[] = [
                    'id' => $rec->paid_by,
                ];
            }

            // filter only active mates
            $included = array_values(array_filter($included, function ($m) use ($mateIds) {
                return in_array((int) $m['id'], $mateIds, true);
            }));

            $count = count($included);
            if ($count === 0) {
                continue;
            }

            $total = (float) $rec->amount;
            $shares = ExpenseSplit::sharePerUser($total, $included);

            foreach ($included as $mate) {
                $id = (int) $mate['id'];
                $split = $shares[$id] ?? 0.0;

                if ($id === (int) $rec->paid_by) {
                    $balance[$id] += $total - $split;
                } else {
                    $balance[$id] -= $split;
                }
            }
        }

        foreach ($balance as $id => $v) {
            $r = round((float) $v, 2);
            $balance[$id] = abs($r) < 0.005 ? 0.0 : $r;
        }

        return $balance;
    }
}