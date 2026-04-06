<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // --- Fetch all records for the month ---
        $records = $house->records()
            ->where('month', $month)
            ->get();

        // --- Extract mates from records JSON ---
        $matesMap = [];

        foreach ($records as $rec) {
            // Add payer
            if (!isset($matesMap[$rec->paid_by])) {
                $matesMap[$rec->paid_by] = $rec->paid_by_name ?? 'Unknown';
            }

            // Add included mates
            $includedMates = is_array($rec->included_mates) ? $rec->included_mates : [];
            foreach ($includedMates as $mate) {
                if (!isset($matesMap[$mate['id']])) {
                    $matesMap[$mate['id']] = $mate['name'] ?? 'Unknown';
                }
            }
        }

        // --- Prepare mates array ---
        $mates = [];
        foreach ($matesMap as $id => $name) {
            $mates[] = ['id' => $id, 'name' => $name];
        }

        $mateIds = array_keys($matesMap);

        // --- Available months ---
        $availableMonths = $house->records()
            ->select('month')
            ->distinct()
            ->orderBy('month', 'desc')
            ->pluck('month')
            ->toArray();

        // --- Paid amounts ---
        $paidAmounts = [];
        foreach ($mates as $mate) {
            $paidAmounts[$mate['id']] = $records
                ->where('paid_by', $mate['id'])
                ->sum('amount');
        }

        // --- Category breakdown ---
        $categoryBreakdown = [];
        foreach ($records as $rec) {
            $payerName = $rec->paid_by_name ?? 'Unknown';
            $category = $rec->category ? $rec->category->name : 'Other';
            $title = $rec->description ?? 'Expense';

            if (!isset($categoryBreakdown[$payerName])) {
                $categoryBreakdown[$payerName] = [];
            }

            if (!isset($categoryBreakdown[$payerName][$category])) {
                $categoryBreakdown[$payerName][$category] = [
                    'total' => 0,
                    'items' => [],
                ];
            }

            $categoryBreakdown[$payerName][$category]['items'][] = [
                'title' => $title,
                'amount' => (float) $rec->amount,
            ];

            $categoryBreakdown[$payerName][$category]['total'] += $rec->amount;
        }

        // --- Calculate balances ---
        $balance = array_fill_keys($mateIds, 0);

        foreach ($records as $rec) {
            $included = is_array($rec->included_mates) ? $rec->included_mates : [];

            // Ensure payer is included
            $foundPayer = collect($included)->firstWhere('id', $rec->paid_by);
            if (!$foundPayer) {
                $included[] = ['id' => $rec->paid_by, 'name' => $rec->paid_by_name];
            }

            // Only include mates in this month's record
            $included = array_filter($included, fn($m) => in_array($m['id'], $mateIds));
            $countIncluded = count($included);
            if ($countIncluded === 0)
                continue;

            $split = $rec->amount / $countIncluded;

            foreach ($included as $mate) {
                $id = $mate['id'];
                if ($id == $rec->paid_by) {
                    $balance[$id] += $rec->amount - $split;
                } else {
                    $balance[$id] -= $split;
                }
            }
        }

        // --- Transactions ---
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

        return response()->json([
            'mates' => $mates,
            'transactions' => $transactions,
            'currency' => $currency,
            'available_months' => $availableMonths,
            'month' => $month,
            'paid_amounts' => $paidAmounts,
            'category_breakdown' => $categoryBreakdown,
        ]);
    }
}