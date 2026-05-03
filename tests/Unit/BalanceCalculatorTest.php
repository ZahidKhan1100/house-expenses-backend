<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BalanceCalculator;
use PHPUnit\Framework\TestCase;
use stdClass;

final class BalanceCalculatorTest extends TestCase
{
    private BalanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BalanceCalculator;
    }

    public function test_equal_split_when_payer_included_receives_difference(): void
    {
        $mateIds = [10, 20];
        $records = [$this->makeEqualRecord(amount: 100.0, paidBy: 10, includedIds: [10, 20])];

        $b = $this->calculator->calculate($records, $mateIds, 100.0);

        self::assertEquals(50.0, $b[10]); // paid 100 - share 50
        self::assertEquals(-50.0, $b[20]);
    }

    public function test_equal_when_payer_excluded_everyone_else_owes_full_amount(): void
    {
        $mateIds = [10, 20];
        // Payer 10 paid $100; only 20 shares — 20 owes all $100 to 10.
        $records = [$this->makeEqualRecord(amount: 100.0, paidBy: 10, includedIds: [20])];

        $b = $this->calculator->calculate($records, $mateIds, 100.0);

        self::assertEquals(100.0, $b[10]);
        self::assertEquals(-100.0, $b[20]);
    }

    public function test_days_weighted_payer_included(): void
    {
        $mateIds = [101, 102];
        $records = [$this->makeDaysRecord(
            amount: 300.0,
            paidBy: 101,
            includedIds: [101, 102],
            billPeriodDays: 30,
            excludedDays: [101 => 0, 102 => 0],
            guestExtra: [101 => 0, 102 => 0],
        )];

        $b = $this->calculator->calculate($records, $mateIds, 100.0);

        self::assertEqualsWithDelta(150.0, $b[101], 0.02);
        self::assertEqualsWithDelta(-150.0, $b[102], 0.02);
    }

    public function test_days_when_payer_excluded_consumer_owes_full_total(): void
    {
        $mateIds = [201, 202];
        $records = [$this->makeDaysRecord(
            amount: 240.0,
            paidBy: 201,
            includedIds: [202],
            billPeriodDays: 31,
            excludedDays: [202 => 0],
            guestExtra: [202 => 0],
        )];

        $b = $this->calculator->calculate($records, $mateIds, 100.0);

        self::assertEqualsWithDelta(240.0, $b[201], 0.02);
        self::assertEqualsWithDelta(-240.0, $b[202], 0.02);
    }

    /**
     * @param  list<int>  $includedIds
     */
    private function makeEqualRecord(float $amount, int $paidBy, array $includedIds): stdClass
    {
        $included = [];
        foreach ($includedIds as $id) {
            $included[] = ['id' => $id];
        }
        $r = new stdClass;
        $r->included_mates = $included;
        $r->paid_by = $paidBy;
        $r->amount = $amount;
        $r->split_method = 'equal';

        return $r;
    }

    /**
     * @param  array<int, int>  $excludedDays
     * @param  array<int, int>  $guestExtra
     */
    private function makeDaysRecord(
        float $amount,
        int $paidBy,
        array $includedIds,
        int $billPeriodDays,
        array $excludedDays,
        array $guestExtra,
    ): stdClass {
        $included = [];
        foreach ($includedIds as $id) {
            $included[] = ['id' => $id];
        }

        // Record model accessors — BalanceCalculator expects these attribute names where split_method is days.
        $excludedByUser = [];
        $guestByUser = [];
        foreach ($excludedDays as $uid => $ex) {
            $excludedByUser[$uid] = $ex;
        }
        foreach ($guestExtra as $uid => $gx) {
            $guestByUser[$uid] = $gx;
        }

        $r = new stdClass;
        $r->included_mates = $included;
        $r->paid_by = $paidBy;
        $r->amount = $amount;
        $r->split_method = 'days';
        $r->bill_period_days = $billPeriodDays;
        $r->excluded_days_by_user = $excludedByUser;
        $r->guest_extra_days_by_user = $guestByUser;

        return $r;
    }
}
