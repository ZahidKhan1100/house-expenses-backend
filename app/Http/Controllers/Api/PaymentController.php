<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\House;
use App\Models\Record;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function index(Request $request, $month = null)
    {
        $user = Auth::user();
        $house = $user->house;

        if (!$house) {
            return response()->json([
                'mates' => [],
                'transactions' => [],
                'currency' => '$',
                'available_months' => [],
                'month' => $month ?? now()->format('Y-m'),
                'paid_amounts' => [],
                'category_breakdown' => [], // ✅ added
            ]);
        }

        $currency = $house->currency ?? '$';
        $month = $month ?? now()->format('Y-m');

        // --- Fetch all mates ---
        $mates = $house->mates()
            ->whereIn('status', ['approved', 'admin'])
            ->get(['id', 'name']);

        if ($house->admin && !$mates->contains('id', $house->admin->id)) {
            $mates->push($house->admin->only(['id', 'name']));
        }

        $mateIds = $mates->pluck('id')->toArray();

        // --- Available months ---
        $availableMonths = $house->records()
            ->select('month')
            ->distinct()
            ->orderBy('month', 'desc')
            ->pluck('month')
            ->toArray();

        // --- Records ---
        $records = $house->records()
            ->where('month', $month)
            ->get();

        // --- Paid amounts ---
        $paidAmounts = [];
        foreach ($mates as $mate) {
            $paidAmounts[$mate->id] = $records
                ->where('paid_by', $mate->id)
                ->sum('amount');
        }

        // ================================
        // 🔥 CATEGORY BREAKDOWN (NEW)
        // ================================
        $categoryBreakdown = [];

        foreach ($records as $rec) {

            $payer = $rec->paid_by;

            if (!in_array($payer, $mateIds)) continue;

            $category = $rec->category ?? 'Other';
            $title = $rec->title ?? 'Expense';

            if (!isset($categoryBreakdown[$payer])) {
                $categoryBreakdown[$payer] = [];
            }

            if (!isset($categoryBreakdown[$payer][$category])) {
                $categoryBreakdown[$payer][$category] = [
                    'total' => 0,
                    'items' => [],
                ];
            }

            // ✅ Add item (Milk, Bread etc.)
            $categoryBreakdown[$payer][$category]['items'][] = [
                'title' => $title,
                'amount' => (float) $rec->amount,
            ];

            // ✅ Add total
            $categoryBreakdown[$payer][$category]['total'] += $rec->amount;
        }

        // --- Calculate balances ---
        $balance = [];
        foreach ($mates as $mate) $balance[$mate->id] = 0;

        foreach ($records as $rec) {
            $included = is_array($rec->included_mates) ? $rec->included_mates : [];

            if (!in_array($rec->paid_by, $included)) {
                $included[] = $rec->paid_by;
            }

            $included = array_filter($included, fn($id) => in_array($id, $mateIds));
            if (count($included) === 0) continue;

            $split = $rec->amount / count($included);

            foreach ($included as $id) {
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
            if ($amt > 0) $creditors[] = ['id' => $id, 'amount' => $amt];
            if ($amt < 0) $debtors[] = ['id' => $id, 'amount' => -$amt];
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

            if ($debtor['amount'] == 0) $i++;
            if ($creditor['amount'] == 0) $j++;
        }

        // --- Final Response ---
        return response()->json([
            'mates' => $mates,
            'transactions' => $transactions,
            'currency' => $currency,
            'available_months' => $availableMonths,
            'month' => $month,
            'paid_amounts' => $paidAmounts,
            'category_breakdown' => $categoryBreakdown, // 🔥 FINAL ADD
        ]);
    }
}