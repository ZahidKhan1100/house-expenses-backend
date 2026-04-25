<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HouseCalendarBlock;
use App\Models\User;
use App\Services\HouseCalendarAnnouncementService;
use App\Services\HouseCalendarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HouseCalendarController extends Controller
{
    public function index(Request $request, HouseCalendarService $calendar)
    {
        $user = Auth::user();
        if (! $user?->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        $month = $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            return response()->json(['message' => 'Invalid month'], 422);
        }

        $houseId = (int) $user->house_id;

        $blocks = HouseCalendarBlock::where('house_id', $houseId)
            ->orderBy('starts_on')
            ->get()
            ->map(function (HouseCalendarBlock $b) {
                return [
                    'id' => $b->id,
                    'user_id' => (int) $b->user_id,
                    'starts_on' => $b->starts_on->format('Y-m-d'),
                    'ends_on' => $b->ends_on->format('Y-m-d'),
                    'kind' => $b->kind,
                    'reason_emoji' => $b->reason_emoji,
                ];
            });

        $summary = $calendar->summarizeMonth($houseId, $month);

        $summaryKeyed = [];
        foreach ($summary as $uid => $row) {
            $summaryKeyed[(string) $uid] = $row;
        }

        return response()->json([
            'month' => $month,
            'blocks' => $blocks,
            'summary' => $summaryKeyed,
        ]);
    }

    public function presence(HouseCalendarService $calendar)
    {
        $user = Auth::user();
        if (! $user?->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        $mates = $calendar->matePresenceToday((int) $user->house_id);

        return response()->json([
            'mates' => $mates,
        ]);
    }

    public function store(Request $request, HouseCalendarAnnouncementService $announcements)
    {
        $user = Auth::user();
        if (! $user?->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'kind' => ['required', Rule::in(['away', 'guest'])],
            'reason_emoji' => ['nullable', 'string', 'max:16'],
        ]);

        $mate = User::where('house_id', $user->house_id)
            ->whereIn('status', User::HOUSE_MEMBER_STATUSES)
            ->where('id', (int) $data['user_id'])
            ->firstOrFail();

        $block = HouseCalendarBlock::create([
            'house_id' => (int) $user->house_id,
            'user_id' => (int) $mate->id,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'kind' => $data['kind'],
            'reason_emoji' => $data['reason_emoji'] ?? null,
        ]);

        try {
            $announcements->announceBlock($user, $block, $block->reason_emoji);
        } catch (\Throwable) {
            // Wall/push are best-effort; block is still saved.
        }

        return response()->json([
            'success' => true,
            'block' => [
                'id' => $block->id,
                'user_id' => (int) $block->user_id,
                'starts_on' => $block->starts_on->format('Y-m-d'),
                'ends_on' => $block->ends_on->format('Y-m-d'),
                'kind' => $block->kind,
                'reason_emoji' => $block->reason_emoji,
            ],
        ], 201);
    }

    public function destroy(int $id)
    {
        $user = Auth::user();
        if (! $user?->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        $block = HouseCalendarBlock::where('house_id', $user->house_id)->findOrFail($id);
        $block->delete();

        return response()->json(['success' => true]);
    }
}
