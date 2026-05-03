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

        $key = sprintf(
            'split_balance:v2:%d:%s:%d:%s:%s:%s',
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
     * @param  float  $guestDayWeightPercent  Each guest night counts as (percent / 100) of one full bill day (100 = legacy 1:1).
     */
    public function calculate($records, array $mateIds, float $guestDayWeightPercent = 100.0): array
    {
        $mateIds = array_map(static fn ($id) => (int) $id, $mateIds);
        $balance = array_fill_keys($mateIds, 0.0);
        $gwp = $guestDayWeightPercent >= 0 ? $guestDayWeightPercent : 0.0;

        foreach ($records as $rec) {

            $included = is_array($rec->included_mates)
                ? $rec->included_mates
                : [];

            // filter only active mates
            $included = array_values(array_filter($included, function ($m) use ($mateIds) {
                return in_array((int) $m['id'], $mateIds, true);
            }));

            $count = count($included);
            if ($count === 0) {
                continue;
            }

            $total = (float) $rec->amount;
            if (($rec->split_method ?? 'equal') === 'days') {
                $excluded = is_array($rec->excluded_days_by_user ?? null) ? $rec->excluded_days_by_user : [];
                $guestExtra = is_array($rec->guest_extra_days_by_user ?? null) ? $rec->guest_extra_days_by_user : [];
                $billDays = (int) ($rec->bill_period_days ?? 0);
                $weighted = array_map(static function ($m) use ($excluded, $guestExtra, $billDays, $gwp) {
                    $id = (int) ($m['id'] ?? 0);
                    $ex = (int) ($excluded[$id] ?? 0);
                    if ($ex < 0) $ex = 0;
                    $gx = (int) ($guestExtra[$id] ?? 0);
                    if ($gx < 0) $gx = 0;
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

            foreach ($included as $mate) {
                $id = (int) $mate['id'];
                $split = $shares[$id] ?? 0.0;

                if ($id === $payerId) {
                    $balance[$id] += $total - $split;
                    $payerIncluded = true;
                } else {
                    $balance[$id] -= $split;
                }
            }

            // Payer floated the full bill but is not splitting (0% consumption share): owed the whole amount by everyone in `included`.
            if (! $payerIncluded && array_key_exists($payerId, $balance)) {
                $balance[$payerId] += $total;
            }
        }

        foreach ($balance as $id => $v) {
            $r = round((float) $v, 2);
            $balance[$id] = abs($r) < 0.005 ? 0.0 : $r;
        }

        return $balance;
    }
}