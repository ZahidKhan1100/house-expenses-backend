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

        $data = Settlement::where('house_id', $user->house_id)
            ->where('month', $request->month)
            ->get();

        return response()->json([
            'success' => true,
            'settlements' => $data,
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