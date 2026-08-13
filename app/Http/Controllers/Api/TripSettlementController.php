<?php

namespace App\Http\Controllers\Api;

use App\Events\TripSettlementPaid;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripSettlement;
use App\Models\User;
use App\Services\ExpoPushService;
use App\Services\TripSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TripSettlementController extends Controller
{
    public function generate(Request $request, $tripId, TripSettlementService $service)
    {
        $trip = $this->authorizedTrip($tripId);
        $this->assertTripActive($trip);

        $result = $service->generate($trip->id);

        return response()->json([
            'success' => true,
            'transactions' => $result['transactions'] ?? [],
            'net_balances' => $result['net_balances'] ?? [],
            'record_count' => $result['record_count'] ?? 0,
        ]);
    }

    public function index(Request $request, $tripId)
    {
        $trip = $this->authorizedTrip($tripId);

        $settlements = TripSettlement::where('trip_id', $trip->id)
            ->orderByDesc('created_at')
            ->get();

        $userIds = $settlements
            ->flatMap(fn (TripSettlement $s) => [$s->from_user_id, $s->to_user_id])
            ->unique()
            ->filter()
            ->values();
        $nameById = User::withTrashed()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $rows = $settlements->map(function (TripSettlement $s) use ($nameById) {
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
            'currency' => $trip->currency ?? '$',
            'settlements' => $rows,
        ]);
    }

    public function markPaid($tripId, $id)
    {
        $trip = $this->authorizedTrip($tripId);
        $this->assertTripActive($trip);
        $user = Auth::user();

        $settlement = TripSettlement::where('id', $id)
            ->where('trip_id', $trip->id)
            ->firstOrFail();

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

        $tripCurrency = $trip->currency ?? '$';
        $amount = round((float) $settlement->amount, 2);

        $otherUserId = $isSender
            ? (int) $settlement->to_user_id
            : (int) $settlement->from_user_id;

        event(new TripSettlementPaid(
            toUserId: $otherUserId,
            fromUserId: (int) $user->id,
            fromName: (string) ($user->name ?? 'Someone'),
            amount: $amount,
            currency: $tripCurrency,
            tripId: (int) $trip->id,
            tripName: (string) ($trip->name ?? 'Trip'),
            settlementId: (int) $settlement->id,
        ));

        $otherUser = User::with('pushTokens')->find($otherUserId);
        if ($otherUser && $otherUser->allExpoPushTokens()->isNotEmpty()) {
            $title = $isSender ? 'Trip settlement received' : 'Trip settlement confirmed';
            $body = $isSender
                ? (($user->name ?? 'Someone') . ' just settled ' . $tripCurrency . number_format($amount, 2) . ' with you on ' . ($trip->name ?? 'your trip') . '! Tap to confirm.')
                : (($user->name ?? 'Someone') . ' confirmed receiving ' . $tripCurrency . number_format($amount, 2) . ' on ' . ($trip->name ?? 'your trip') . '. All settled!');

            Log::info('Sending push', [
                'type' => 'trip_settlement.paid',
                'to_user_id' => $otherUserId,
                'trip_id' => (int) $trip->id,
                'settlement_id' => (int) $settlement->id,
                'marked_by_role' => $isSender ? 'sender' : 'receiver',
            ]);
            app(ExpoPushService::class)->sendToUserDevices(
                $otherUser,
                $title,
                $body,
                [
                    'type' => 'trip_settlement.paid',
                    'settlementId' => $settlement->id,
                    'tripId' => (int) $trip->id,
                ],
            );
        } else {
            Log::info('Push skipped (no expo token)', [
                'type' => 'trip_settlement.paid',
                'to_user_id' => $otherUserId,
                'trip_id' => (int) $trip->id,
                'settlement_id' => (int) $settlement->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settlement marked as paid',
        ]);
    }

    private function authorizedTrip($tripId): Trip
    {
        $trip = Trip::findOrFail($tripId);

        $isMember = (int) $trip->admin_id === Auth::id() || $trip->members()->where('users.id', Auth::id())->exists();
        abort_unless($isMember, 403, 'You are not a member of this trip.');

        return $trip;
    }

    private function assertTripActive(Trip $trip): void
    {
        abort_if($trip->status === 'archived', 422, 'This trip has ended and can no longer be modified.');
    }
}
