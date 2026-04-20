<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Models\User;
use App\Services\ExpenseSplit;
use App\Services\ExpoPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BuybackController extends Controller
{
    /**
     * Create a manual "stock buy-back" transfer(s) and show it in Settlements.
     *
     * Body:
     * - title: string (e.g. "Router buy-back")
     * - note: string|null (optional extra context)
     * - month: YYYY-MM (optional; defaults to current month)
     * - amount: number (total)
     * - participant_user_ids: int[] (who should reimburse; defaults to all mates except buyer)
     * - buyer_user_id: int|null (defaults to current user)
     */
    public function store(Request $request)
    {
        $buyer = Auth::user();
        if (! $buyer?->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'buyer_user_id' => ['nullable', 'integer'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['integer'],
        ]);

        $month = $validated['month'] ?? now()->format('Y-m');
        $amount = round((float) $validated['amount'], 2);

        if (! empty($validated['buyer_user_id'])) {
            $buyer = User::where('house_id', $buyer->house_id)->findOrFail((int) $validated['buyer_user_id']);
        }

        $mateIds = User::where('house_id', $buyer->house_id)
            ->whereIn('status', ['approved', 'admin'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $participants = $validated['participant_user_ids'] ?? array_values(array_filter($mateIds, fn ($id) => (int) $id !== (int) $buyer->id));
        $participants = array_values(array_unique(array_map('intval', $participants)));
        $participants = array_values(array_filter($participants, fn ($id) => in_array((int) $id, $mateIds, true) && (int) $id !== (int) $buyer->id));

        if (count($participants) === 0) {
            return response()->json(['message' => 'Select at least one participant'], 422);
        }

        // Whole-cent split so row amounts always sum to $amount (same rules as bill splits).
        $includedOrdered = array_map(static fn (int $id) => ['id' => $id], $participants);
        $sharesByUserId = ExpenseSplit::sharePerUser($amount, $includedOrdered);

        $rows = DB::transaction(function () use ($buyer, $participants, $validated, $month, $sharesByUserId) {
            $buyerName = (string) ($buyer->name ?? 'Someone');
            $title = $validated['title'];
            $note = $validated['note'] ?? null;

            $created = [];

            foreach ($participants as $fromId) {
                $from = User::find($fromId);
                if (! $from) continue;

                $share = round((float) ($sharesByUserId[(int) $from->id] ?? 0), 2);
                if ($share < 0.01) {
                    continue;
                }

                $created[] = Settlement::create([
                    'house_id' => (int) $buyer->house_id,
                    'month' => (string) $month,
                    'from_user_id' => (int) $from->id,
                    'to_user_id' => (int) $buyer->id,
                    'from_name' => (string) ($from->name ?? 'Unknown'),
                    'to_name' => $buyerName,
                    'amount' => $share,
                    'source' => 'manual',
                    'type' => 'stock_buyback',
                    'title' => $title,
                    'note' => $note,
                    'status' => 'pending',
                ]);
            }

            return $created;
        });

        $sharesPayload = collect($sharesByUserId)
            ->mapWithKeys(fn ($v, $uid) => [(string) $uid => round((float) $v, 2)])
            ->all();

        // Push notify participants (best-effort).
        try {
            $push = app(ExpoPushService::class);
            $buyerName = (string) ($buyer->name ?? 'Someone');
            $houseCurrency = $buyer->house?->currency ?? '$';
            $title = (string) ($validated['title'] ?? 'Stock buy-back');

            $toNotify = User::query()
                ->where('house_id', (int) $buyer->house_id)
                ->whereIn('id', $participants)
                ->with('pushTokens')
                ->get(['id', 'name', 'expo_push_token']);

            foreach ($toNotify as $mate) {
                if ($mate->allExpoPushTokens()->isEmpty()) continue;
                $push->sendToUserDevices(
                    $mate,
                    'Stock buy-back added',
                    $buyerName . ' added a buy-back: ' . $title . ' · ' . $houseCurrency . number_format($amount, 2) . '. Tap to view.',
                    [
                        'type' => 'stock_buyback',
                        'month' => (string) $month,
                        'buyer_user_id' => (int) $buyer->id,
                        'title' => $title,
                        'amount' => $amount,
                    ],
                );
            }
        } catch (\Throwable) {
            // ignore
        }

        return response()->json([
            'success' => true,
            'count' => count($rows),
            'shares' => $sharesPayload,
        ], 201);
    }
}

