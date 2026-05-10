<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\KarmaService;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        if (!$me->house_id) {
            return response()->json(['success' => true, 'users' => []]);
        }

        $rows = User::query()
            ->where('house_id', $me->house_id)
            ->whereIn('status', User::HOUSE_MEMBER_STATUSES)
            ->orderByDesc('karma_balance')
            ->orderBy('created_at')
            ->get(['id', 'name', 'email', 'is_founder', 'karma_balance', 'avatar_url']);

        $karma = app(KarmaService::class);

        $users = $rows->map(function (User $u) use ($karma) {
            $bal = (int) ($u->karma_balance ?? 0);
            $avatar = $u->avatar_url ? trim((string) $u->avatar_url) : '';
            return [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => $avatar !== '' ? $avatar : null,
                'is_founder' => (bool) $u->is_founder,
                'karma_balance' => $bal,
                'level' => $karma->levelFor($bal),
            ];
        })->values();

        $houseLegendId = $users->isNotEmpty() ? (int) $users->first()['id'] : null;

        return response()->json([
            'success' => true,
            'house_legend_user_id' => $houseLegendId,
            'users' => $users,
        ]);
    }
}

