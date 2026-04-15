<?php

namespace App\Services;

use App\Models\House;
use App\Models\User;

class LeaderboardLeaderNotifier
{
    /**
     * After karma changes, detect if #1 changed and notify other housemates (not the new #1).
     */
    public function syncAfterKarmaChange(User $user): void
    {
        if (!$user->house_id) {
            return;
        }

        $house = House::query()->find($user->house_id);
        if (!$house) {
            return;
        }

        $top = User::query()
            ->where('house_id', $house->id)
            ->whereIn('status', ['approved', 'admin'])
            ->orderByDesc('karma_balance')
            ->orderBy('created_at')
            ->first(['id', 'name']);

        $newId = $top?->id;
        $prevId = $house->leaderboard_top_user_id;

        if ($newId === null) {
            if ($prevId !== null) {
                $house->leaderboard_top_user_id = null;
                $house->save();
            }
            return;
        }

        if ($prevId !== null && (int) $prevId === (int) $newId) {
            return;
        }

        $house->leaderboard_top_user_id = $newId;
        $house->save();

        $name = $top->name ?? 'Someone';
        $title = 'House Legend shake-up';
        $body = 'Uh oh! ' . $name . ' just took the #1 spot as House Legend. Better settle those bills! 🏆';

        $push = app(ExpoPushService::class);

        $mates = User::query()
            ->where('house_id', $house->id)
            ->whereIn('status', ['approved', 'admin'])
            ->where('id', '!=', $newId)
            ->get(['expo_push_token']);

        foreach ($mates as $m) {
            $token = $m->expo_push_token ?? '';
            if ($token === '') {
                continue;
            }
            try {
                $push->send(
                    expoToken: $token,
                    title: $title,
                    body: $body,
                    data: [
                        'type' => 'leaderboard.overtake',
                        'house_legend_user_id' => (int) $newId,
                    ],
                );
            } catch (\Throwable $e) {
            }
        }
    }
}
