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

        $userIds = $settlements
            ->flatMap(fn (Settlement $s) => [$s->from_user_id, $s->to_user_id])
            ->unique()
            ->filter()
            ->values();
        $nameById = User::withTrashed()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        // Full history for the month: pending = planned transfers, paid = completed.
        // Amounts are not recomputed from expenses here — each row is a stored transfer.
        // Prefer live names (incl. soft-deleted accounts) over stale snapshots.
        $rows = $settlements->map(function (Settlement $s) use ($nameById) {
            $fromName = $nameById->get($s->from_user_id)?->name ?? $s->from_name ?? 'Unknown';
            $toName = $nameById->get($s->to_user_id)?->name ?? $s->to_name ?? 'Unknown';

            return [
                'id' => $s->id,
                'from_user_id' => $s->from_user_id,
                'to_user_id' => $s->to_user_id,
                'from_name' => $fromName,
                'to_name' => $toName,
                'amount' => round((float) $s->amount, 2),
                'source' => $s->source ?? null,
                'type' => $s->type ?? null,
                'title' => $s->title ?? null,
                'note' => $s->note ?? null,
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

        // Only the sender (who pays) or the receiver (who confirms) can mark
        // a transfer as paid. Other housemates (and admins not party to the
        // transfer) cannot toggle other people's settlements.
        $authUserId = (int) $user->id;
        $isSender = $authUserId === (int) $settlement->from_user_id;
        $isReceiver = $authUserId === (int) $settlement->to_user_id;

        if (!$isSender && !$isReceiver) {
            return response()->json([
                'success' => false,
                'message' => 'Only the sender or receiver can mark this settlement as paid.',
            ], 403);
        }

        if ((string) $settlement->status === 'paid') {
            return response()->json([
                'success' => true,
                'message' => 'Already marked as paid.',
            ]);
        }

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

        // Notify the OTHER party (the one who didn't mark it): if the sender
        // marks paid, ping the receiver to confirm; if the receiver marks
        // paid (acknowledging they got the money), ping the sender that the
        // transfer is closed.
        $houseCurrency = $user->house?->currency ?? '$';
        $amount = round((float) $settlement->amount, 2);

        $otherUserId = $isSender
            ? (int) $settlement->to_user_id
            : (int) $settlement->from_user_id;

        event(new SettlementPaid(
            toUserId: $otherUserId,
            fromUserId: (int) $user->id,
            fromName: (string) ($user->name ?? 'Someone'),
            amount: $amount,
            currency: $houseCurrency,
            month: (string) $settlement->month,
            settlementId: (int) $settlement->id,
        ));

        $otherUser = User::with('pushTokens')->find($otherUserId);
        if ($otherUser && $otherUser->allExpoPushTokens()->isNotEmpty()) {
            $title = $isSender ? 'Settlement received' : 'Settlement confirmed';
            $body = $isSender
                ? (($user->name ?? 'A mate') . ' just settled ' . $houseCurrency . number_format($amount, 2) . ' with you! Tap to confirm.')
                : (($user->name ?? 'A mate') . ' confirmed receiving ' . $houseCurrency . number_format($amount, 2) . '. All settled!');

            Log::info('Sending push', [
                'type' => 'settlement.paid',
                'to_user_id' => $otherUserId,
                'house_id' => (int) $user->house_id,
                'settlement_id' => (int) $settlement->id,
                'marked_by_role' => $isSender ? 'sender' : 'receiver',
            ]);
            app(ExpoPushService::class)->sendToUserDevices(
                $otherUser,
                $title,
                $body,
                [
                    'type' => 'settlement.paid',
                    'settlementId' => $settlement->id,
                    'month' => $settlement->month,
                ],
            );
        } else {
            Log::info('Push skipped (no expo token)', [
                'type' => 'settlement.paid',
                'to_user_id' => $otherUserId,
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