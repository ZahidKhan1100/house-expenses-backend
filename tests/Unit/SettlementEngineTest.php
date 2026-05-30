<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ExpenseSplit;
use App\Services\SettlementEngine;
use PHPUnit\Framework\TestCase;

final class SettlementEngineTest extends TestCase
{
    public function test_transfer_totals_match_net_balances_in_cents(): void
    {
        $balances = [
            1 => 47.36,
            2 => 0.01,
            3 => -23.73,
            4 => -23.64,
        ];

        $engine = new SettlementEngine;
        $tx = $engine->optimize($balances);

        $paidOut = [];
        $paidIn = [];
        foreach ($tx as $row) {
            $from = (int) $row['from_user_id'];
            $to = (int) $row['to_user_id'];
            $cents = (int) round(((float) $row['amount']) * 100);
            $paidOut[$from] = ($paidOut[$from] ?? 0) + $cents;
            $paidIn[$to] = ($paidIn[$to] ?? 0) + $cents;
        }

        foreach ($balances as $userId => $net) {
            $netCents = (int) round($net * 100);
            if ($netCents > 0) {
                self::assertSame($netCents, $paidIn[$userId] ?? 0, "creditor {$userId}");
            } elseif ($netCents < 0) {
                self::assertSame(-$netCents, $paidOut[$userId] ?? 0, "debtor {$userId}");
            }
        }

        $sumOut = array_sum($paidOut);
        $sumIn = array_sum($paidIn);
        self::assertSame($sumOut, $sumIn);
    }

    public function test_four_way_equal_bill_net_zero_and_settles(): void
    {
        $included = [
            ['id' => 10],
            ['id' => 20],
            ['id' => 30],
            ['id' => 40],
        ];
        $shares = ExpenseSplit::sharePerUser(94.83, $included);

        self::assertEqualsWithDelta(94.83, array_sum($shares), 0.001);

        $balance = [
            10 => 94.83 - ($shares[10] ?? 0),
            20 => -($shares[20] ?? 0),
            30 => -($shares[30] ?? 0),
            40 => -($shares[40] ?? 0),
        ];

        foreach ($balance as $id => $v) {
            $balance[$id] = round((float) $v, 2);
        }

        $tx = (new SettlementEngine)->optimize($balance);
        self::assertNotEmpty($tx);
        self::assertCount(3, $tx);
    }
}
