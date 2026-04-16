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
use App\Services\HouseWallGoalService;
use App\Services\KarmaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        // Karma: Instant Settler +50 if paid within 12 hours of row creation.
        try {
            $createdAt = $settlement->created_at ?? null;
            if ($createdAt && now()->diffInHours($createdAt) <= 12) {
                app(KarmaService::class)->add($user, 50, 'instant_settler');
            }
        } catch (\Throwable $e) {
            // best-effort; settlement must still succeed
        }

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
            Log::info('Sending push', [
                'type' => 'settlement.paid',
                'to_user_id' => (int) $receiver->id,
                'house_id' => (int) $user->house_id,
                'settlement_id' => (int) $settlement->id,
            ]);
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
        } else {
            Log::info('Push skipped (no expo token)', [
                'type' => 'settlement.paid',
                'to_user_id' => (int) ($settlement->to_user_id ?? 0),
                'house_id' => (int) $user->house_id,
                'settlement_id' => (int) $settlement->id,
            ]);
        }

        try {
            app(HouseWallGoalService::class)->maybePostHouseGoalAfterSettlement(
                (int) $user->house_id,
                (string) $settlement->month,
            );
        } catch (\Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Settlement marked as paid',
        ]);
    }
}