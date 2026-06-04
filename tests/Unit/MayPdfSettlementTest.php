<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BalanceCalculator;
use App\Services\ExpenseSplit;
use App\Services\SettlementEngine;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Reference PDF amounts with test user ids 1–6 (Satbir = 5 excluded from Meat).
 * Production azam KHAN house uses ids 3–8 — see {@see AzamHouseMay2026SettlementTest}.
 */
final class MayPdfSettlementTest extends TestCase
{
    private BalanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BalanceCalculator;
    }

    public function test_may_pdf_settlement_transfers_match_exact_cent_split(): void
    {
        $mateIds = [1, 2, 3, 4, 5, 6];
        $records = self::mayPdfRecords();

        $balances = $this->calculator->calculate($records, $mateIds, 100.0);
        $tx = (new SettlementEngine)->optimize($balances);

        self::assertEqualsWithDelta(0.0, array_sum($balances), 0.001);

        $byKey = [];
        foreach ($tx as $row) {
            $key = $row['from_user_id'].'-'.$row['to_user_id'];
            $byKey[$key] = (float) $row['amount'];
        }

        self::assertEqualsWithDelta(295.93, $byKey['4-2'] ?? 0.0, 0.01);
        self::assertEqualsWithDelta(2.12, $byKey['3-2'] ?? 0.0, 0.01);
        self::assertEqualsWithDelta(127.16, $byKey['3-5'] ?? 0.0, 0.01);
        self::assertEqualsWithDelta(11.84, $byKey['3-6'] ?? 0.0, 0.01);
        self::assertEqualsWithDelta(45.64, $byKey['1-6'] ?? 0.0, 0.01);
        self::assertCount(5, $tx);
    }

    public function test_abid_net_matches_exact_per_bill_shares_not_floored_sum_plus_debtor_penalty(): void
    {
        $mateIds = [1, 2, 3, 4, 5, 6];
        $all = array_map(static fn (int $id) => ['id' => $id], $mateIds);
        $meat = array_values(array_filter($all, static fn (array $m) => $m['id'] !== 5));

        $exactOwed = 0.0;
        foreach (self::mayBillSpecs() as [$amount, $payer, $useMeat]) {
            $included = $useMeat ? $meat : $all;
            $exactOwed += ExpenseSplit::sharePerUser($amount, $included)[4] ?? 0.0;
        }

        $balances = $this->calculator->calculate(self::mayPdfRecords(), $mateIds, 100.0);

        self::assertEqualsWithDelta($exactOwed, abs($balances[4]), 0.01);
        self::assertEqualsWithDelta(-295.93, $balances[4], 0.01);
        self::assertLessThan(0.15, abs($balances[4]) - 295.90);
    }

    public function test_orphan_cents_split_per_bill_not_all_on_largest_debtor(): void
    {
        $included = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
            ['id' => 4],
            ['id' => 6],
        ];

        $legacy = ExpenseSplit::distributeOrphanCentsToDebtors(34, [
            1 => -4552,
            3 => -14104,
            4 => -29590,
        ]);

        $perBill = ExpenseSplit::distributeOrphanCentsAmongIncluded(3, $included);

        self::assertSame(21, $legacy[4] ?? 0);
        self::assertLessThan(3, $perBill[4] ?? 0);
        self::assertSame(3, array_sum($perBill));
    }

    public function test_distribute_orphan_among_included_sums_to_orphan_total(): void
    {
        $included = [['id' => 10], ['id' => 20], ['id' => 30]];
        $extra = ExpenseSplit::distributeOrphanCentsAmongIncluded(4, $included);

        self::assertSame(4, array_sum($extra));
    }

    /** @return list<stdClass> */
    private static function mayPdfRecords(): array
    {
        $records = [];
        foreach (self::mayBillSpecs() as [$amount, $paidBy, $useMeat]) {
            $records[] = self::makeEqualRecord($amount, $paidBy, $useMeat);
        }

        return $records;
    }

    /**
     * @return list<array{0: float, 1: int, 2: bool}>
     */
    private static function mayBillSpecs(): array
    {
        return [
            [2.0, 3, false],
            [3.75, 3, false],
            [10.0, 3, false],
            [6.0, 3, false],
            [36.19, 3, false],
            [26.67, 3, false],
            [70.25, 3, true],
            [164.43, 1, false],
            [85.95, 1, true],
            [257.60, 6, false],
            [95.78, 6, true],
            [8.67, 5, false],
            [364.0, 5, false],
            [274.58, 2, false],
            [19.48, 2, false],
            [300.0, 2, false],
        ];
    }

    private static function makeEqualRecord(float $amount, int $paidBy, bool $meatOnlyFive): stdClass
    {
        $mateIds = [1, 2, 3, 4, 5, 6];
        $all = array_map(static fn (int $id) => ['id' => $id], $mateIds);
        $included = $meatOnlyFive
            ? array_values(array_filter($all, static fn (array $m) => $m['id'] !== 5))
            : $all;

        $r = new stdClass;
        $r->included_mates = $included;
        $r->paid_by = $paidBy;
        $r->amount = $amount;
        $r->split_method = 'equal';

        return $r;
    }
}
