<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BalanceCalculator;
use App\Services\ExpenseSplit;
use App\Services\SettlementEngine;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ExactCentSplitTest extends TestCase
{
    public function test_610_euro_three_people_exact_shares(): void
    {
        $shares = ExpenseSplit::sharePerUser(610.0, [
            ['id' => 10],
            ['id' => 20],
            ['id' => 30],
        ]);

        self::assertEqualsWithDelta(203.34, $shares[10], 0.001);
        self::assertEqualsWithDelta(203.33, $shares[20], 0.001);
        self::assertEqualsWithDelta(203.33, $shares[30], 0.001);
        self::assertEqualsWithDelta(610.0, array_sum($shares), 0.001);
    }

    public function test_210_euro_two_people_then_added_to_full_house_bill(): void
    {
        $mateIds = [10, 20, 30];
        $all = array_map(static fn (int $id) => ['id' => $id], $mateIds);
        $twoOnly = [['id' => 10], ['id' => 20]];

        $records = [
            self::bill(610.0, 10, $all),
            self::bill(210.0, 20, $twoOnly),
        ];

        $calc = new BalanceCalculator;
        $balances = $calc->calculate($records, $mateIds, 100.0);

        self::assertEqualsWithDelta(105.0, ExpenseSplit::sharePerUser(210.0, $twoOnly)[10], 0.001);
        self::assertEqualsWithDelta(105.0, ExpenseSplit::sharePerUser(210.0, $twoOnly)[20], 0.001);

        // 10 paid 610, owes share 203.34 + owes 105 on second bill = net creditor on first dominates
        self::assertEqualsWithDelta(0.0, array_sum($balances), 0.01);

        $tx = (new SettlementEngine)->optimize($balances);
        self::assertNotEmpty($tx);
    }

    public function test_full_house_bills_processed_before_partial_in_two_bill_month(): void
    {
        $mateIds = [1, 2, 3];
        $all = [['id' => 1], ['id' => 2], ['id' => 3]];
        $partialFirst = [
            self::bill(30.0, 1, [['id' => 1], ['id' => 2]]),
            self::bill(30.0, 2, $all),
        ];
        $fullFirst = [
            self::bill(30.0, 2, $all),
            self::bill(30.0, 1, [['id' => 1], ['id' => 2]]),
        ];

        $calc = new BalanceCalculator;
        $bPartialFirst = $calc->calculate($partialFirst, $mateIds, 100.0);
        $bFullFirst = $calc->calculate($fullFirst, $mateIds, 100.0);

        self::assertEquals($bPartialFirst, $bFullFirst);
    }

    /** @param  list<array{id: int}>  $included */
    private static function bill(float $amount, int $paidBy, array $included): stdClass
    {
        $r = new stdClass;
        $r->amount = $amount;
        $r->paid_by = $paidBy;
        $r->included_mates = $included;
        $r->split_method = 'equal';

        return $r;
    }
}
