<?php

namespace App\Services;

use App\Models\HouseCalendarBlock;
use App\Models\User;
use Carbon\Carbon;

class HouseCalendarService
{
    /**
     * Billable-day math for a calendar month: away = fewer person-days; guest = extra person-days (Plus One).
     *
     * @return array<int, array{away_days: int, guest_extra_days: int}>
     */
    public function summarizeMonth(int $houseId, string $ym): array
    {
        $start = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $ym)->endOfMonth();

        $blocks = HouseCalendarBlock::query()
            ->where('house_id', $houseId)
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->get(['user_id', 'starts_on', 'ends_on', 'kind']);

        $byUser = [];

        foreach ($blocks->groupBy('user_id') as $userId => $userBlocks) {
            $away = [];
            $guest = [];
            foreach ($userBlocks as $b) {
                $is = Carbon::parse($b->starts_on)->max($start);
                $ie = Carbon::parse($b->ends_on)->min($end);
                if ($is->gt($ie)) {
                    continue;
                }
                for ($d = $is->copy(); $d->lte($ie); $d->addDay()) {
                    $key = $d->toDateString();
                    if ($b->kind === 'guest') {
                        $guest[$key] = true;
                    } else {
                        $away[$key] = true;
                    }
                }
            }
            $uid = (int) $userId;
            $byUser[$uid] = [
                'away_days' => count($away),
                'guest_extra_days' => count($guest),
            ];
        }

        return $byUser;
    }

    /**
     * Who is away or hosting a guest today (for House Wall presence strip).
     *
     * @return array<int, array{user_id: int, name: string, presence: string, away_until: ?string, guest_plus: bool}>
     */
    public function matePresenceToday(int $houseId): array
    {
        $today = Carbon::today();

        $mates = User::query()
            ->where('house_id', $houseId)
            ->whereIn('status', User::HOUSE_MEMBER_STATUSES)
            ->orderBy('name')
            ->get(['id', 'name']);

        $blocks = HouseCalendarBlock::query()
            ->where('house_id', $houseId)
            ->where('starts_on', '<=', $today->toDateString())
            ->where('ends_on', '>=', $today->toDateString())
            ->get();

        $out = [];
        foreach ($mates as $mate) {
            $uid = (int) $mate->id;
            $away = $blocks->first(fn ($b) => (int) $b->user_id === $uid && $b->kind === 'away');
            $guest = $blocks->first(fn ($b) => (int) $b->user_id === $uid && $b->kind === 'guest');

            if ($away) {
                $out[] = [
                    'user_id' => $uid,
                    'name' => (string) $mate->name,
                    'presence' => 'away',
                    'away_until' => Carbon::parse($away->ends_on)->format('Y-m-d'),
                    'guest_plus' => false,
                ];
            } else {
                $out[] = [
                    'user_id' => $uid,
                    'name' => (string) $mate->name,
                    'presence' => 'home',
                    'away_until' => null,
                    'guest_plus' => $guest !== null,
                ];
            }
        }

        return $out;
    }
}
