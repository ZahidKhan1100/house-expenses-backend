<?php

namespace App\Services;

use App\Events\HouseWallPostCreated;
use App\Models\Expense;
use App\Models\HouseWallPost;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;

class HouseWallGoalService
{
    /**
     * When all settlements for a month are paid, post a celebratory system card (once per month).
     */
    public function maybePostHouseGoalAfterSettlement(int $houseId, string $month): void
    {
        $pending = Settlement::query()
            ->where('house_id', $houseId)
            ->where('month', $month)
            ->where('status', 'pending')
            ->count();

        if ($pending > 0) {
            return;
        }

        $paid = Settlement::query()
            ->where('house_id', $houseId)
            ->where('month', $month)
            ->where('status', 'paid')
            ->count();

        if ($paid === 0) {
            return;
        }

        $exists = HouseWallPost::query()
            ->where('house_id', $houseId)
            ->where('type', 'system')
            ->where('system_payload->kind', 'house_goal')
            ->where('system_payload->month', $month)
            ->exists();

        if ($exists) {
            return;
        }

        $expense = Expense::query()
            ->where('house_id', $houseId)
            ->where('month', $month)
            ->first();

        $topCategory = 'House';
        $isRent = false;

        if ($expense) {
            $row = DB::table('records')
                ->join('categories', 'records.category_id', '=', 'categories.id')
                ->where('records.expense_id', $expense->id)
                ->selectRaw('categories.name as name, SUM(records.amount) as total')
                ->groupBy('categories.id', 'categories.name')
                ->orderByDesc('total')
                ->first();

            if ($row && $row->name) {
                $topCategory = (string) $row->name;
                $isRent = stripos($topCategory, 'rent') !== false;
            }
        }

        $caption = $isRent
            ? 'House Goal Smashed! Rent is 100% settled. 🥂'
            : 'House Goal Smashed! Month is fully settled — ' . $topCategory . ' was top spend. 🥂';

        if (strlen($caption) > 100) {
            $caption = mb_substr($caption, 0, 97) . '…';
        }

        DB::transaction(function () use ($houseId, $month, $caption, $topCategory, $isRent) {
            $post = HouseWallPost::create([
                'house_id' => $houseId,
                'user_id' => null,
                'type' => 'system',
                'caption' => $caption,
                'system_payload' => [
                    'kind' => 'house_goal',
                    'month' => $month,
                    'highlight_category' => $topCategory,
                    'rent_highlight' => $isRent,
                ],
            ]);

            $payload = [
                'id' => $post->id,
                'type' => $post->type,
                'caption' => $post->caption,
                'image_url' => null,
                'poll_question' => null,
                'poll_options' => [],
                'counts' => [],
                'my_vote_option_id' => null,
                'hearts_count' => 0,
                'my_hearted' => false,
                'emoji_counts' => [],
                'my_emojis' => [],
                'user' => null,
                'created_at' => $post->created_at?->toISOString(),
                'system_payload' => $post->system_payload,
            ];

            DB::afterCommit(fn () => event(new HouseWallPostCreated((int) $houseId, $post, $payload)));
        });
    }
}
