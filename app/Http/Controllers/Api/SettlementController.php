<?php

// app/Http/Controllers/Api/SettlementController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SettlementService;
use App\Models\Settlement;
use Illuminate\Support\Facades\Auth;

class SettlementController extends Controller
{
    public function generate(Request $request, SettlementService $service)
    {
        $user = Auth::user();
        $month = $request->month;


        $transactions = $service->generate($user->house_id, $month);

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $settlements = Settlement::where('house_id', $user->house_id)
            ->where('month', $request->month)
            ->get();

        // ----------------------------
        // PAID AMOUNTS MAP
        // ----------------------------
        $paidMap = [];

        foreach ($settlements->where('status', 'paid') as $paid) {
            $key = $paid->from_user_id . '-' . $paid->to_user_id;

            if (!isset($paidMap[$key])) {
                $paidMap[$key] = 0;
            }

            $paidMap[$key] += $paid->amount;
        }

        // ----------------------------
        // BUILD FINAL PENDING LIST
        // ----------------------------
        $pending = [];

        foreach ($settlements->where('status', 'pending') as $s) {

            $key = $s->from_user_id . '-' . $s->to_user_id;

            $paidAmount = $paidMap[$key] ?? 0;

            $remaining = $s->amount - $paidAmount;

            if ($remaining > 0.01) {
                $pending[] = [
                    'id' => $s->id,
                    'from_user_id' => $s->from_user_id,
                    'to_user_id' => $s->to_user_id,
                    'from_name' => $s->from_name,
                    'to_name' => $s->to_name,
                    'amount' => round($remaining, 2),
                    'status' => 'pending',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'settlements' => $pending,
        ]);
    }

    public function markPaid($id)
    {
        $user = auth()->user();

        $settlement = Settlement::where('id', $id)
            ->where('house_id', $user->house_id)
            ->firstOrFail();

        $settlement->update([
            'status' => 'paid',
            'settled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settlement marked as paid',
        ]);
    }
}