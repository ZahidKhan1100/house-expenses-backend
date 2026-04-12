<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{


    public function index(Request $request, $month = null)
    {
        $user = Auth::user();
        $house = $user->house;

        $month = $month ?? now()->format('Y-m');

        if (!$house) {
            return response()->json([
                'mates' => [],
                'transactions' => [],
                'currency' => '$',
                'available_months' => [],
                'month' => $month,
                'paid_amounts' => [],
                'category_breakdown' => [],
            ]);
        }

        $currency = $house->currency ?? '$';

        // ✅ Get records
        $records = $house->records()
            ->with(['category', 'expense'])
            ->whereHas('expense', function ($q) use ($month) {
                $q->where('month', $month);
            })
            ->get();

        // =========================
        // ✅ MATES (FROM RECORDS)
        // =========================
        $matesMap = [];

        foreach ($records as $rec) {
            $matesMap[$rec->paid_by] = $rec->paid_by_name ?? 'Unknown';

            $included = is_array($rec->included_mates) ? $rec->included_mates : [];

            foreach ($included as $mate) {
                $matesMap[$mate['id']] = $mate['name'] ?? 'Unknown';
            }
        }

        $mates = collect($matesMap)
            ->map(fn($name, $id) => ['id' => $id, 'name' => $name])
            ->values();

        $mateIds = array_keys($matesMap);

        // =========================
        // ✅ AVAILABLE MONTHS
        // =========================
        $availableMonths = $house->records()
            ->selectRaw("DATE_FORMAT(timestamp, '%Y-%m') as month")
            ->distinct()
            ->orderBy('month', 'desc')
            ->pluck('month');

        // =========================
        // ✅ PAID AMOUNTS
        // =========================
        $paidAmounts = [];

        foreach ($mates as $mate) {
            $paidAmounts[$mate['id']] = $records
                ->where('paid_by', $mate['id'])
                ->sum('amount');
        }

        // =========================
        // ✅ CATEGORY BREAKDOWN
        // =========================
        $categoryBreakdown = [];

        foreach ($records as $rec) {
            $payer = $rec->paid_by_name ?? 'Unknown';
            $category = $rec->category->name ?? 'Other';
            $title = $rec->description ?? 'Expense';

            $categoryBreakdown[$payer][$category]['items'][] = [
                'title' => $title,
                'amount' => (float) $rec->amount,
            ];

            $categoryBreakdown[$payer][$category]['total'] =
                ($categoryBreakdown[$payer][$category]['total'] ?? 0) + $rec->amount;
        }

        // =========================
        // ✅ BALANCE CALCULATION
        // =========================
        $balance = array_fill_keys($mateIds, 0);

        foreach ($records as $rec) {
            $included = is_array($rec->included_mates) ? $rec->included_mates : [];

            // ensure payer included
            if (!collect($included)->firstWhere('id', $rec->paid_by)) {
                $included[] = [
                    'id' => $rec->paid_by,
                    'name' => $rec->paid_by_name
                ];
            }

            $count = count($included);
            if ($count === 0)
                continue;

            $split = $rec->amount / $count;

            foreach ($included as $mate) {
                $id = $mate['id'];

                if ($id == $rec->paid_by) {
                    $balance[$id] += $rec->amount - $split;
                } else {
                    $balance[$id] -= $split;
                }
            }
        }

        // =========================
        // ✅ SUBTRACT COMPLETED SETTLEMENTS (REAL PAYMENTS)
        // =========================
        $balance = app(SettlementService::class)->applyPaidSettlementsToNetBalances(
            $house->id,
            $month,
            $balance,
        );

        // =========================
        // ✅ TRANSACTIONS (REMAINING NET AFTER PAID SETTLEMENTS)
        // =========================
        $creditors = [];
        $debtors = [];

        foreach ($balance as $id => $amt) {
            if ($amt > 0)
                $creditors[] = ['id' => $id, 'amount' => $amt];
            if ($amt < 0)
                $debtors[] = ['id' => $id, 'amount' => -$amt];
        }

        $transactions = [];
        $i = $j = 0;

        while ($i < count($debtors) && $j < count($creditors)) {
            $debtor = &$debtors[$i];
            $creditor = &$creditors[$j];

            $amt = min($debtor['amount'], $creditor['amount']);

            $transactions[] = [
                'from' => $debtor['id'],
                'to' => $creditor['id'],
                'amount' => round($amt, 2),
            ];

            $debtor['amount'] -= $amt;
            $creditor['amount'] -= $amt;

            if ($debtor['amount'] == 0)
                $i++;
            if ($creditor['amount'] == 0)
                $j++;
        }

        // Ensure names exist for anyone appearing only in settlement-adjusted flows
        $txUserIds = collect($transactions)
            ->flatMap(fn ($tx) => [$tx['from'], $tx['to']])
            ->unique()
            ->all();
        $existingMateIds = $mates->pluck('id')->all();
        foreach ($txUserIds as $tid) {
            if (!in_array($tid, $existingMateIds, true)) {
                $mates->push([
                    'id' => $tid,
                    'name' => User::find($tid)?->name ?? 'Unknown',
                ]);
            }
        }

        return response()->json([
            'mates' => $mates->values(),
            'transactions' => $transactions,
            'currency' => $currency,
            'available_months' => $availableMonths,
            'month' => $month,
            'paid_amounts' => $paidAmounts,
            'category_breakdown' => $categoryBreakdown,
        ]);
    }
}