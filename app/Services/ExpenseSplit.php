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

    /**
     * Weighted cents-safe split (e.g. day-based shares).
     *
     * @param array<int, array{id:int|string, weight:int|float}> $includedWeighted stable order (remainder cents go to earlier entries)
     * @return array<int, float> user id => share in dollars (2 dp)
     */
    public static function sharePerUserWeighted(float $total, array $includedWeighted): array
    {
        $includedWeighted = array_values($includedWeighted);
        if (count($includedWeighted) === 0) {
            return [];
        }

        $centsTotal = (int) round($total * 100);
        $weights = array_map(static function ($m) {
            $w = (float) ($m['weight'] ?? 0);
            return $w > 0 ? $w : 0.0;
        }, $includedWeighted);
        $sum = array_sum($weights);

        // Avoid divide-by-zero: fallback to equal split.
        if ($sum <= 0) {
            return self::sharePerUser($total, $includedWeighted);
        }

        $rawCents = [];
        $baseCents = [];
        $fractions = [];
        $allocated = 0;

        foreach ($includedWeighted as $i => $m) {
            $portion = ($weights[$i] / $sum) * $centsTotal;
            $floor = (int) floor($portion);
            $rawCents[$i] = $portion;
            $baseCents[$i] = $floor;
            $fractions[$i] = $portion - $floor;
            $allocated += $floor;
        }

        $remainder = $centsTotal - $allocated;

        // Distribute remaining cents to largest fractional parts; tie-breaker = earlier stable order.
        if ($remainder > 0) {
            arsort($fractions, SORT_NUMERIC);
            foreach (array_keys($fractions) as $i) {
                if ($remainder <= 0) break;
                $baseCents[$i] += 1;
                $remainder -= 1;
            }
        }

        $out = [];
        foreach ($includedWeighted as $i => $mate) {
            $id = (int) $mate['id'];
            $out[$id] = ($baseCents[$i] ?? 0) / 100.0;
        }

        return $out;
    }
}
