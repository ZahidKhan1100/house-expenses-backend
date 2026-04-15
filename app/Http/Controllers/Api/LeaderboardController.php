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
            ->whereIn('status', ['approved', 'admin'])
            ->orderByDesc('karma_balance')
            ->orderBy('created_at')
            ->get(['id', 'name', 'email', 'is_founder', 'karma_balance']);

        $karma = app(KarmaService::class);

        $users = $rows->map(function (User $u) use ($karma) {
            $bal = (int) ($u->karma_balance ?? 0);
            return [
                'id' => $u->id,
                'name' => $u->name,
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

