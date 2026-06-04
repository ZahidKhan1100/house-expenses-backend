<?php

// app/Services/BalanceCalculator.php

namespace App\Services;

use App\Models\House;
use Illuminate\Support\Facades\Cache;

class BalanceCalculator
{
    /**
     * Cache net balances for a house/month. Key version bumps when any record in the set changes.
     */
    public function calculateWithCache(int $houseId, string $month, $records, array $mateIds): array
    {
        $guestDayWeightPercent = (float) (House::query()->whereKey($houseId)->value('guest_day_weight_percent') ?? 100.0);

        $cfg = config('houseexpenses.split_balance_cache', []);
        if (empty($cfg['enabled'])) {
            return $this->calculate($records, $mateIds, $guestDayWeightPercent);
        }

        $col = collect($records);
        $maxTs = $col->max(function ($r) {
            $u = $r->updated_at ?? $r->created_at ?? null;
            if ($u instanceof \DateTimeInterface) {
                return $u->format('Y-m-d H:i:s.u');
            }

            return (string) ($u ?? '');
        });
        $count = $col->count();
        $ids = $col->pluck('id')->filter()->sort()->values()->implode(',');

        $version = (string) config('houseexpenses.split_algorithm_version', 'v6-exact-cents');

        $key = sprintf(
            'split_balance:%s:%d:%s:%d:%s:%s:%s',
            $version,
            $houseId,
            $month,
            $count,
            md5((string) $maxTs),
            md5($ids),
            md5((string) $guestDayWeightPercent)
        );

        $ttl = (int) ($cfg['ttl'] ?? 3600);
        $storeName = $cfg['store'] ?? null;
        $cache = $storeName ? Cache::store($storeName) : Cache::store();

        return $cache->remember($key, max(60, $ttl), fn () => $this->calculate($records, $mateIds, $guestDayWeightPercent));
    }

    /**
     * Net balances for the month. Each bill splits to the exact cent among people on that bill
     * (e.g. €610÷3 → €203.34 + €203.33 + €203.33). Full-house bills are applied first, then
     * partial-category bills add on top.
     *
     * @param  float  $guestDayWeightPercent  Each guest night counts as (percent / 100) of one full bill day (100 = legacy 1:1).
     */
    public function calculate($records, array $mateIds, float $guestDayWeightPercent = 100.0): array
    {
        $mateIds = array_map(static fn ($id) => (int) $id, $mateIds);
        $balanceCents = array_fill_keys($mateIds, 0);
        $gwp = $guestDayWeightPercent >= 0 ? $guestDayWeightPercent : 0.0;

        $fullHouse = [];
        $partial = [];

        foreach ($records as $rec) {
            $included = $this->filterIncludedMates($rec, $mateIds);
            if ($included === []) {
                continue;
            }

            if ($this->isFullHouseBill($included, $mateIds)) {
                $fullHouse[] = [$rec, $included];
            } else {
                $partial[] = [$rec, $included];
            }
        }

        foreach ([$fullHouse, $partial] as $group) {
            foreach ($group as [$rec, $included]) {
                $this->applyBillToBalanceCents($rec, $included, $balanceCents, $gwp);
            }
        }

        $balance = [];
        foreach ($balanceCents as $id => $cents) {
            $balance[$id] = abs($cents) < 1 ? 0.0 : round($cents / 100.0, 2);
        }

        return $balance;
    }

    /** @param  list<int>  $mateIds */
    private function isFullHouseBill(array $included, array $mateIds): bool
    {
        if (count($included) !== count($mateIds)) {
            return false;
        }

        $onBill = array_map(static fn (array $m) => (int) $m['id'], $included);
        sort($onBill);
        $house = $mateIds;
        sort($house);

        return $onBill === $house;
    }

    /** @param  list<int>  $mateIds
     * @return list<array{id: int}>
     */
    private function filterIncludedMates(object $rec, array $mateIds): array
    {
        $included = is_array($rec->included_mates ?? null) ? $rec->included_mates : [];

        $filtered = array_values(array_filter($included, function ($m) use ($mateIds) {
            return in_array((int) ($m['id'] ?? 0), $mateIds, true);
        }));

        return ExpenseSplit::sortIncludedMates($filtered);
    }

    /**
     * @param  list<array{id: int|string}>  $included
     * @param  array<int, int>  $balanceCents
     */
    private function applyBillToBalanceCents(object $rec, array $included, array &$balanceCents, float $gwp): void
    {
        $total = (float) $rec->amount;

        if (($rec->split_method ?? 'equal') === 'days') {
            $excluded = is_array($rec->excluded_days_by_user ?? null) ? $rec->excluded_days_by_user : [];
            $guestExtra = is_array($rec->guest_extra_days_by_user ?? null) ? $rec->guest_extra_days_by_user : [];
            $billDays = (int) ($rec->bill_period_days ?? 0);
            $weighted = array_map(static function ($m) use ($excluded, $guestExtra, $billDays, $gwp) {
                $id = (int) ($m['id'] ?? 0);
                $ex = max(0, (int) ($excluded[$id] ?? 0));
                $gx = max(0, (int) ($guestExtra[$id] ?? 0));
                $guestPart = $gx * ($gwp / 100.0);
                $eff = max(0, $billDays - $ex) + $guestPart;

                return ['id' => $id, 'weight' => $eff];
            }, $included);
            $shares = ExpenseSplit::sharePerUserWeighted($total, $weighted);
        } else {
            $shares = ExpenseSplit::sharePerUser($total, $included);
        }

        $payerId = (int) $rec->paid_by;
        $payerIncluded = false;
        $totalCents = (int) round($total * 100);

        foreach ($included as $mate) {
            $id = (int) $mate['id'];
            $splitCents = (int) round(((float) ($shares[$id] ?? 0.0)) * 100);

            if ($id === $payerId) {
                $balanceCents[$id] += $totalCents - $splitCents;
                $payerIncluded = true;
            } else {
                $balanceCents[$id] -= $splitCents;
            }
        }

        if (! $payerIncluded && array_key_exists($payerId, $balanceCents)) {
            $balanceCents[$payerId] += $totalCents;
        }
    }
}
