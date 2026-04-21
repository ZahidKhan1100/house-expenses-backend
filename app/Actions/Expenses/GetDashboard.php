<?php

namespace App\Actions\Expenses;

use App\Models\Record;
use App\Models\User;
use App\Services\BalanceCalculator;
use App\Services\SettlementService;

class GetDashboard
{
    public function handle(User $user): array
    {
        $house = $user->house;

        if (!$house) {
            return [
                'total_spent' => 0,
                'currency' => '$',
                'categories' => [],
                'mates' => [],
                'month' => now()->format('Y-m'),
                'latest_bill' => null,
                'split_balance' => null,
            ];
        }

        // ✅ CURRENT MONTH (you can later pass from frontend if needed)
        $month = now()->format('Y-m');

        // ✅ Get ONLY current month expense
        $expenseMonth = $house->expenses()
            ->where('month', $month)
            ->with(['records.category'])
            ->first();

        $records = $expenseMonth?->records ?? collect();

        // --- Categories ---
        $categories = $house->categories()
            ->get()
            ->mapWithKeys(function ($cat) {
                return [
                    $cat->id => [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'icon' => $cat->icon,
                        'total' => 0,
                    ]
                ];
            })
            ->toArray();

        $totalSpent = 0;

        // --- Process only current month records ---
        foreach ($records as $record) {

            $totalSpent += $record->amount;

            if ($record->category_id && isset($categories[$record->category_id])) {
                $categories[$record->category_id]['total'] += $record->amount;
            }
        }

        // --- Mates ---
        $mates = $house->mates()
            ->get()
            ->map(function ($mate) {
                return [
                    'id' => $mate->id,
                    'name' => $mate->name,
                    'role' => $mate->role,
                ];
            });

        $latestBill = Record::query()
            ->whereHas('expense', function ($q) use ($house) {
                $q->where('house_id', $house->id);
            })
            ->with('category')
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->first();

        $latestPayload = null;
        if ($latestBill) {
            $latestPayload = [
                'id' => $latestBill->id,
                'description' => $latestBill->description,
                'amount' => round((float) $latestBill->amount, 2),
                'paid_by_name' => $latestBill->paid_by_name,
                'category_name' => $latestBill->category?->name,
                'timestamp' => $latestBill->timestamp
                    ? $latestBill->timestamp->toIso8601String()
                    : null,
            ];
        }

        $splitBalancePayload = null;
        if ($records->isNotEmpty()) {
            $matesMap = [];
            foreach ($records as $rec) {
                $matesMap[(int) $rec->paid_by] = true;
                $included = is_array($rec->included_mates) ? $rec->included_mates : [];
                foreach ($included as $mate) {
                    $id = (int) ($mate['id'] ?? 0);
                    if ($id > 0) {
                        $matesMap[$id] = true;
                    }
                }
            }
            $mateIds = array_keys($matesMap);
            if (!empty($mateIds)) {
                $balance = app(BalanceCalculator::class)->calculateWithCache(
                    (int) $house->id,
                    $month,
                    $records,
                    $mateIds,
                );
                $balance = app(SettlementService::class)->applyPaidSettlementsToNetBalances(
                    (int) $house->id,
                    $month,
                    $balance,
                );
                $uid = (int) $user->id;
                $net = round((float) ($balance[$uid] ?? 0.0), 2);
                $splitBalancePayload = [
                    'month' => $month,
                    'net' => $net,
                ];
            }
        }

        return [
            'total_spent' => round($totalSpent, 2),
            'currency' => $house->currency ?? '$',
            'categories' => array_values($categories),
            'mates' => $mates,
            'month' => $month,
            'latest_bill' => $latestPayload,
            'split_balance' => $splitBalancePayload,
        ];
    }
}