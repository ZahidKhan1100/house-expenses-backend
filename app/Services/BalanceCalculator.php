<?php

// app/Services/BalanceCalculator.php

namespace App\Services;

use App\Models\Record;

class BalanceCalculator
{
    public function calculate($records, array $mateIds): array
    {
        $balance = array_fill_keys($mateIds, 0);

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
            $included = array_filter($included, function ($m) use ($mateIds) {
                return in_array($m['id'], $mateIds);
            });

            $count = count($included);
            if ($count === 0) continue;

            $split = $rec->amount / $count;

            foreach ($included as $mate) {
                $id = $mate['id'];

                if ($id == $rec->paid_by) {
                    $balance[$id] += $rec->amount - $split;
                } else {
                    $balance[$id] -= $split;
                }
            }
        }

        return $balance;
    }
}