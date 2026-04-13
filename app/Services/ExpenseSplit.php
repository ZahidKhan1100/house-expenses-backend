<?php

namespace App\Services;

/**
 * Split a total into per-person shares in whole cents so the parts always sum to the total.
 */
class ExpenseSplit
{
    /**
     * @param  array<int, array{id: int|string}>  $includedOrdered  Participants in stable order (remainder cents go to earlier entries).
     * @return array<int, float> user id => share in dollars (2 dp)
     */
    public static function sharePerUser(float $total, array $includedOrdered): array
    {
        $includedOrdered = array_values($includedOrdered);
        $n = count($includedOrdered);
        if ($n === 0) {
            return [];
        }

        $centsTotal = (int) round($total * 100);
        $base = intdiv($centsTotal, $n);
        $remainder = $centsTotal % $n;

        $out = [];
        foreach ($includedOrdered as $i => $mate) {
            $id = (int) $mate['id'];
            $cents = $base + ($i < $remainder ? 1 : 0);
            $out[$id] = $cents / 100.0;
        }

        return $out;
    }
}
