<?php

namespace App\Services;

/**
 * Split totals into per-person shares in whole cents.
 *
 * Per-bill recording uses floored splits (everyone the same base on equal bills); leftover
 * cents for the month are applied once via {@see distributeOrphanCentsToDebtors()}.
 */
class ExpenseSplit
{
    /**
     * Equal split: floor cents per person; leftover cents are returned separately.
     *
     * @param  array<int, array{id: int|string}>  $includedOrdered
     * @return array{shares: array<int, float>, orphan_cents: int}
     */
    public static function sharePerUserFloored(float $total, array $includedOrdered): array
    {
        $includedOrdered = array_values($includedOrdered);
        $n = count($includedOrdered);
        if ($n === 0) {
            return ['shares' => [], 'orphan_cents' => 0];
        }

        $centsTotal = (int) round($total * 100);
        $base = intdiv($centsTotal, $n);
        $orphanCents = $centsTotal - ($base * $n);

        $shares = [];
        foreach ($includedOrdered as $mate) {
            $id = (int) $mate['id'];
            $shares[$id] = $base / 100.0;
        }

        return ['shares' => $shares, 'orphan_cents' => $orphanCents];
    }

    /**
     * Weighted split: floor each share; leftover cents returned separately.
     *
     * @param  array<int, array{id: int|string, weight: int|float}>  $includedWeighted
     * @return array{shares: array<int, float>, orphan_cents: int}
     */
    public static function sharePerUserWeightedFloored(float $total, array $includedWeighted): array
    {
        $includedWeighted = array_values($includedWeighted);
        if (count($includedWeighted) === 0) {
            return ['shares' => [], 'orphan_cents' => 0];
        }

        $centsTotal = (int) round($total * 100);
        $weights = array_map(static function ($m) {
            $w = (float) ($m['weight'] ?? 0);

            return $w > 0 ? $w : 0.0;
        }, $includedWeighted);
        $sum = array_sum($weights);

        if ($sum <= 0) {
            return self::sharePerUserFloored($total, $includedWeighted);
        }

        $baseCents = [];
        foreach ($includedWeighted as $i => $m) {
            $portion = ($weights[$i] / $sum) * $centsTotal;
            $baseCents[$i] = (int) floor($portion);
        }

        $orphanCents = $centsTotal - array_sum($baseCents);

        $shares = [];
        foreach ($includedWeighted as $i => $mate) {
            $id = (int) $mate['id'];
            $shares[$id] = ($baseCents[$i] ?? 0) / 100.0;
        }

        return ['shares' => $shares, 'orphan_cents' => $orphanCents];
    }

    /**
     * Assign leftover month cents to net debtors (by debt weight, then stable id order).
     *
     * @param  array<int, int>  $balanceCents  user id => net cents (negative = owes)
     * @return array<int, int> extra cents owed per user id
     */
    public static function distributeOrphanCentsToDebtors(int $orphanCents, array $balanceCents): array
    {
        if ($orphanCents <= 0) {
            return [];
        }

        $debtors = [];
        foreach ($balanceCents as $id => $cents) {
            if ($cents < 0) {
                $debtors[] = ['id' => (int) $id, 'weight' => -$cents];
            }
        }

        if ($debtors === []) {
            return [];
        }

        usort($debtors, static fn ($a, $b) => $a['id'] <=> $b['id']);

        $extraShares = self::sharePerUserWeighted($orphanCents / 100.0, $debtors);
        $extraCents = [];
        foreach ($extraShares as $id => $amount) {
            $extraCents[(int) $id] = (int) round($amount * 100);
        }

        return $extraCents;
    }

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
