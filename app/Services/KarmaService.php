<?php

namespace App\Services;

use App\Events\KarmaUpdated;
use App\Models\KarmaLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KarmaService
{
    /**
     * Add karma points to a user. Founder status is unchanged.
     */
    public function add(User $user, int $points, string $reason = ''): User
    {
        if ($points === 0) return $user;

        $before = (int) ($user->karma_balance ?? 0);

        // Atomic increment to avoid races under concurrent events
        User::where('id', $user->id)->update([
            'karma_balance' => DB::raw('GREATEST(0, COALESCE(karma_balance, 0) + ' . (int) $points . ')'),
        ]);

        $updated = $user->refresh();
        $after = (int) ($updated->karma_balance ?? 0);
        $delta = $after - $before;

        if ($delta !== 0 && $user->house_id) {
            try {
                KarmaLedger::create([
                    'user_id' => $user->id,
                    'house_id' => (int) $user->house_id,
                    'points' => $delta,
                    'reason' => $reason,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
            }
        }

        // Realtime: tell the user instantly (best-effort)
        try {
            event(new KarmaUpdated(
                userId: (int) $updated->id,
                delta: $delta,
                karmaBalance: $after,
                level: $this->levelFor($after),
                reason: (string) $reason,
            ));
        } catch (\Throwable $e) {
        }

        try {
            app(LeaderboardLeaderNotifier::class)->syncAfterKarmaChange($updated);
        } catch (\Throwable $e) {
        }

        return $updated;
    }

    public function levelFor(int $karmaBalance): int
    {
        // Level 1 = 0..499, Level 2 = 500..999, etc.
        return intdiv(max(0, $karmaBalance), 500) + 1;
    }
}

