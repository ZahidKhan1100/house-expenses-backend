<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BalanceCalculator;
use App\Services\ExpenseSplit;
use App\Services\SettlementEngine;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Production house #2 (azam KHAN's House) — May 2026 — 16 records from Railway audit.
 * Satbir (6) excluded from Meat only. User ids: 3=azam, 4=Amir, 5=Zahid, 6=Satbir, 7=Abid, 8=Junaid.
 *
 * Equal splits pool by included mates: all grocery ÷6, all rent ÷6, all meat ÷5.
 */
final class AzamHouseMay2026SettlementTest extends TestCase
{
    private BalanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BalanceCalculator;
    }

    public function test_grocery_rent_meat_pooled_by_six_and_five(): void
    {
        $mateIds = [3, 4, 5, 6, 7, 8];
        $all = array_map(static fn (int $id) => ['id' => $id], $mateIds);
        $meat = array_values(array_filter($all, static fn (array $m) => $m['id'] !== 6));

        $grocery = 809.37;
        $rent = 664.0;
        $meatTotal = 251.98;

        foreach ([3, 5] as $id) {
            $share = ExpenseSplit::sharePerUser($grocery, $all)[$id]
                + ExpenseSplit::sharePerUser($rent, $all)[$id]
                + ExpenseSplit::sharePerUser($meatTotal, $meat)[$id];
            self::assertEqualsWithDelta(295.97, $share, 0.01, "user {$id} pooled grocery+rent+meat");
        }

        $abidShare = ExpenseSplit::sharePerUser($grocery, $all)[7]
            + ExpenseSplit::sharePerUser($rent, $all)[7]
            + ExpenseSplit::sharePerUser($meatTotal, $meat)[7];
        self::assertEqualsWithDelta(295.94, $abidShare, 0.01);
        self::assertLessThan(0.05, abs($abidShare - 295.97));

        self::assertEqualsWithDelta(
            245.56,
            ExpenseSplit::sharePerUser($grocery, $all)[6] + ExpenseSplit::sharePerUser($rent, $all)[6],
            0.02,
        );
    }

    public function test_may_2026_transfers_match_category_pool_engine(): void
    {
        $mateIds = [3, 4, 5, 6, 7, 8];
        $balances = $this->calculator->calculate(collect(self::productionRecords()), $mateIds, 100.0);
        $tx = (new SettlementEngine)->optimize($balances);

        self::assertEqualsWithDelta(0.0, array_sum($balances), 0.001);

        $byKey = [];
        foreach ($tx as $row) {
            $key = $row['from_user_id'].'-'.$row['to_user_id'];
            $byKey[$key] = (float) $row['amount'];
        }

        self::assertEqualsWithDelta(-295.95, $balances[7], 0.01);
        self::assertEqualsWithDelta(-141.10, $balances[5], 0.01);
        self::assertEqualsWithDelta(295.95, $byKey['7-8'] ?? 0.0, 0.01);
        self::assertEqualsWithDelta(2.16, $byKey['5-8'] ?? 0.0, 0.01);
        self::assertEqualsWithDelta(127.11, $byKey['5-6'] ?? 0.0, 0.01);
        self::assertEqualsWithDelta(11.83, $byKey['5-4'] ?? 0.0, 0.01);
        self::assertEqualsWithDelta(45.59, $byKey['3-4'] ?? 0.0, 0.01);
        self::assertCount(5, $tx);
    }

    /** @return list<stdClass> */
    private static function productionRecords(): array
    {
        $mateIds = [3, 4, 5, 6, 7, 8];
        $all = array_map(static fn (int $id) => ['id' => $id], $mateIds);
        $meat = array_values(array_filter($all, static fn (array $m) => $m['id'] !== 6));

        /** @var list<array{float, int, bool}> $specs amount, payer, fullHouse */
        $specs = [
            [2.00, 5, true],
            [3.75, 5, true],
            [10.00, 5, true],
            [6.00, 5, true],
            [36.19, 5, true],
            [70.25, 5, false],
            [26.67, 5, true],
            [164.43, 3, true],
            [85.95, 3, false],
            [274.58, 8, true],
            [8.67, 6, true],
            [257.60, 4, true],
            [95.78, 4, false],
            [19.48, 8, true],
            [300.00, 8, true],
            [364.00, 6, true],
        ];

        $records = [];
        foreach ($specs as [$amount, $paidBy, $fullHouse]) {
            $r = new stdClass;
            $r->amount = $amount;
            $r->paid_by = $paidBy;
            $r->included_mates = $fullHouse ? $all : $meat;
            $r->split_method = 'equal';

            $records[] = $r;
        }

        return $records;
    }
}
