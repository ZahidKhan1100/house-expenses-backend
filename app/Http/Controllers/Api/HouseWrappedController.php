<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\HouseWallPollVote;
use App\Models\HouseWallPost;
use App\Models\User;
use App\Services\KarmaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HouseWrappedController extends Controller
{
    /**
     * Monthly shareable summary (defaults to current calendar month).
     */
    public function index(Request $request, KarmaService $karma)
    {
        $me = $request->user();
        if (!$me->house_id) {
            return response()->json(['success' => false, 'message' => 'No house'], 400);
        }

        $month = $request->query('month');
        if ($month && !preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            return response()->json(['success' => false, 'message' => 'Invalid month'], 422);
        }

        if (!$month) {
            $month = Carbon::now()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $house = $me->house;
        $houseName = $house?->name ?? 'Your House';
        $currency = $house?->currency ?? '$';

        $expense = Expense::query()
            ->where('house_id', $me->house_id)
            ->where('month', $month)
            ->first();

        $pizzaCount = 0;
        $recordCount = 0;
        if ($expense) {
            $recordCount = (int) DB::table('records')->where('expense_id', $expense->id)->count();
            $pizzaCount = (int) DB::table('records')
                ->join('categories', 'records.category_id', '=', 'categories.id')
                ->where('records.expense_id', $expense->id)
                ->whereRaw('LOWER(categories.name) LIKE ?', ['%pizza%'])
                ->count();
        }

        $totalHouseSpend = (float) DB::table('records')
            ->join('expenses', 'records.expense_id', '=', 'expenses.id')
            ->where('expenses.house_id', $me->house_id)
            ->where('expenses.month', $month)
            ->sum('records.amount');

        $pollVotes = (int) HouseWallPollVote::query()
            ->whereHas('post', function ($q) use ($me) {
                $q->where('house_id', $me->house_id);
            })
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $wallPosts = (int) HouseWallPost::query()
            ->where('house_id', $me->house_id)
            ->whereIn('type', ['snippet', 'poll'])
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $monthlyEarned = DB::table('karma_ledger')
            ->selectRaw('user_id, SUM(points) as earned')
            ->where('house_id', $me->house_id)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('user_id')
            ->havingRaw('SUM(points) > 0')
            ->orderByDesc('earned')
            ->orderBy('user_id')
            ->first();

        $top = null;
        if ($monthlyEarned) {
            $top = User::query()
                ->where('id', $monthlyEarned->user_id)
                ->where('house_id', $me->house_id)
                ->whereIn('status', User::HOUSE_MEMBER_STATUSES)
                ->first(['id', 'name', 'karma_balance']);
        }

        if (!$top) {
            $top = User::query()
                ->where('house_id', $me->house_id)
                ->whereIn('status', User::HOUSE_MEMBER_STATUSES)
                ->orderByDesc('karma_balance')
                ->orderBy('created_at')
                ->first(['id', 'name', 'karma_balance']);
        }

        $karmaKingName = $top?->name ?? '—';
        $karmaKingBalance = (int) ($top?->karma_balance ?? 0);
        $karmaKingLevel = $karma->levelFor($karmaKingBalance);
        $karmaEarnedMonth = $monthlyEarned ? (int) $monthlyEarned->earned : null;

        $mostActivePoll = null;
        $polls = HouseWallPost::query()
            ->where('house_id', $me->house_id)
            ->where('type', 'poll')
            ->where('created_at', '<=', $end)
            ->get(['id', 'poll_question']);

        $bestScore = -1;
        foreach ($polls as $poll) {
            $v = (int) HouseWallPollVote::query()
                ->where('post_id', $poll->id)
                ->whereBetween('created_at', [$start, $end])
                ->count();
            $h = (int) DB::table('house_wall_reactions')
                ->where('post_id', $poll->id)
                ->whereBetween('created_at', [$start, $end])
                ->count();
            $e = (int) DB::table('house_wall_emoji_reactions')
                ->where('post_id', $poll->id)
                ->whereBetween('created_at', [$start, $end])
                ->count();
            $score = $v + $h + $e;
            if ($score > $bestScore) {
                $bestScore = $score;
                $mostActivePoll = [
                    'post_id' => (int) $poll->id,
                    'question' => (string) ($poll->poll_question ?? ''),
                    'engagement_count' => $score,
                ];
            }
        }

        $spendFormatted = $currency . number_format($totalHouseSpend, (floor($totalHouseSpend) == $totalHouseSpend) ? 0 : 2);

        $pollLine = $mostActivePoll
            ? sprintf(
                'Hottest poll: “%s” (%d reactions)',
                mb_strlen($mostActivePoll['question']) > 42
                    ? mb_substr($mostActivePoll['question'], 0, 40) . '…'
                    : $mostActivePoll['question'],
                $mostActivePoll['engagement_count'],
            )
            : 'No poll buzz this month — start one on the House Wall.';

        return response()->json([
            'success' => true,
            'month' => $month,
            'house_name' => $houseName,
            'currency' => $currency,
            'total_house_spend' => round($totalHouseSpend, 2),
            'total_house_spend_formatted' => $spendFormatted,
            'stats' => [
                'expense_records' => $recordCount,
                'pizza_moments' => $pizzaCount,
                'poll_votes' => $pollVotes,
                'wall_posts' => $wallPosts,
            ],
            'karma_king' => [
                'user_id' => $top?->id,
                'name' => $karmaKingName,
                'karma_balance' => $karmaKingBalance,
                'level' => $karmaKingLevel,
                'karma_earned_month' => $karmaEarnedMonth,
            ],
            'most_active_poll' => $mostActivePoll,
            'share_line' => sprintf(
                '%s spent %s total. Karma King: %s. %s',
                $houseName,
                $spendFormatted,
                $karmaKingName,
                $pollLine,
            ),
        ]);
    }
}
