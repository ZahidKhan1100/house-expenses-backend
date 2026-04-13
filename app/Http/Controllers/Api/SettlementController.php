<?php

// app/Http/Controllers/Api/SettlementController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\SettlementService;
use App\Models\Settlement;
use App\Events\SettlementPaid;
use App\Models\User;
use App\Services\ExpoPushService;
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
        $house = $user->house;

        $settlements = Settlement::where('house_id', $user->house_id)
            ->where('month', $request->month)
            ->orderByDesc('created_at')
            ->get();

        // Full history for the month: pending = planned transfers, paid = completed.
        // Amounts are not recomputed from expenses here — each row is a stored transfer.
        $rows = $settlements->map(function (Settlement $s) {
            return [
                'id' => $s->id,
                'from_user_id' => $s->from_user_id,
                'to_user_id' => $s->to_user_id,
                'from_name' => $s->from_name,
                'to_name' => $s->to_name,
                'amount' => round((float) $s->amount, 2),
                'status' => $s->status,
                'settled_at' => $s->settled_at,
            ];
        });

        return response()->json([
            'success' => true,
            'currency' => $house->currency ?? '$',
            'settlements' => $rows,
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

        // Notify only the receiver (to_user_id) in realtime and via push.
        $houseCurrency = $user->house?->currency ?? '$';
        $amount = round((float) $settlement->amount, 2);

        event(new SettlementPaid(
            toUserId: (int) $settlement->to_user_id,
            fromUserId: (int) $user->id,
            fromName: (string) ($user->name ?? 'Someone'),
            amount: $amount,
            currency: $houseCurrency,
            month: (string) $settlement->month,
            settlementId: (int) $settlement->id,
        ));

        $receiver = User::find($settlement->to_user_id);
        if ($receiver?->expo_push_token) {
            app(ExpoPushService::class)->send(
                expoToken: $receiver->expo_push_token,
                title: 'Settlement received',
                body: ($user->name ?? 'A mate') . ' just settled ' . $houseCurrency . number_format($amount, 2) . ' with you! Tap to confirm.',
                data: [
                    'type' => 'settlement.paid',
                    'settlementId' => $settlement->id,
                    'month' => $settlement->month,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Settlement marked as paid',
        ]);
    }
}