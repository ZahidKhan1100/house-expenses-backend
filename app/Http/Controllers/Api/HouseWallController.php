<?php

namespace App\Http\Controllers\Api;

use App\Events\HouseWallHeartToggled;
use App\Events\HouseWallPollVoted;
use App\Events\HouseWallPostCreated;
use App\Http\Controllers\Controller;
use App\Models\HouseWallPollOption;
use App\Models\HouseWallPollVote;
use App\Models\HouseWallPost;
use App\Models\HouseWallReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HouseWallController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) {
            return response()->json(['success' => true, 'posts' => []]);
        }

        $posts = HouseWallPost::query()
            ->where('house_id', $user->house_id)
            ->with(['user:id,name', 'pollOptions:id,post_id,text,sort_order'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $postIds = $posts->pluck('id')->all();

        $heartsCounts = HouseWallReaction::query()
            ->selectRaw('post_id, COUNT(*) as c')
            ->whereIn('post_id', $postIds)
            ->groupBy('post_id')
            ->pluck('c', 'post_id');

        $myHearts = HouseWallReaction::query()
            ->whereIn('post_id', $postIds)
            ->where('user_id', $user->id)
            ->pluck('post_id')
            ->flip();

        $voteCounts = HouseWallPollVote::query()
            ->selectRaw('post_id, option_id, COUNT(*) as c')
            ->whereIn('post_id', $postIds)
            ->groupBy('post_id', 'option_id')
            ->get()
            ->groupBy('post_id')
            ->map(function ($rows) {
                $out = [];
                foreach ($rows as $r) $out[(int) $r->option_id] = (int) $r->c;
                return $out;
            });

        $myVotes = HouseWallPollVote::query()
            ->whereIn('post_id', $postIds)
            ->where('user_id', $user->id)
            ->pluck('option_id', 'post_id');

        $payload = $posts->map(function (HouseWallPost $p) use ($heartsCounts, $myHearts, $voteCounts, $myVotes) {
            return [
                'id' => $p->id,
                'type' => $p->type,
                'caption' => $p->caption,
                'image_url' => $p->image_url,
                'poll_question' => $p->poll_question,
                'poll_options' => $p->pollOptions
                    ->sortBy('sort_order')
                    ->values()
                    ->map(fn ($o) => ['id' => $o->id, 'text' => $o->text])
                    ->all(),
                'counts' => $voteCounts->get($p->id, []),
                'my_vote_option_id' => $myVotes[$p->id] ?? null,
                'hearts_count' => (int) ($heartsCounts[$p->id] ?? 0),
                'my_hearted' => isset($myHearts[$p->id]),
                'user' => $p->user ? ['id' => $p->user->id, 'name' => $p->user->name] : null,
                'created_at' => $p->created_at?->toISOString(),
                'system_payload' => $p->system_payload,
            ];
        });

        return response()->json(['success' => true, 'posts' => $payload]);
    }

    public function createSnippet(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) return response()->json(['message' => 'No house'], 400);

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:100'],
            'image_url' => ['required', 'string', 'max:2048'],
        ]);

        return DB::transaction(function () use ($user, $data) {
            $post = HouseWallPost::create([
                'house_id' => $user->house_id,
                'user_id' => $user->id,
                'type' => 'snippet',
                'caption' => $data['caption'] ?? null,
                'image_url' => $data['image_url'],
            ])->load('user:id,name');

            $payload = [
                'id' => $post->id,
                'type' => $post->type,
                'caption' => $post->caption,
                'image_url' => $post->image_url,
                'poll_question' => null,
                'poll_options' => [],
                'counts' => [],
                'my_vote_option_id' => null,
                'hearts_count' => 0,
                'my_hearted' => false,
                'user' => ['id' => $post->user->id, 'name' => $post->user->name],
                'created_at' => $post->created_at?->toISOString(),
                'system_payload' => null,
            ];

            DB::afterCommit(fn () => event(new HouseWallPostCreated((int) $user->house_id, $post, $payload)));

            return response()->json(['success' => true, 'post' => $payload], 201);
        });
    }

    public function createPoll(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) return response()->json(['message' => 'No house'], 400);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:180'],
            'options' => ['required', 'array', 'min:2', 'max:4'],
            'options.*' => ['required', 'string', 'max:120'],
        ]);

        return DB::transaction(function () use ($user, $data) {
            $post = HouseWallPost::create([
                'house_id' => $user->house_id,
                'user_id' => $user->id,
                'type' => 'poll',
                'poll_question' => $data['question'],
            ]);

            $options = [];
            foreach (array_values($data['options']) as $i => $text) {
                $opt = HouseWallPollOption::create([
                    'post_id' => $post->id,
                    'text' => $text,
                    'sort_order' => $i,
                ]);
                $options[] = ['id' => $opt->id, 'text' => $opt->text];
            }

            $post = $post->load('user:id,name');

            $payload = [
                'id' => $post->id,
                'type' => $post->type,
                'caption' => null,
                'image_url' => null,
                'poll_question' => $post->poll_question,
                'poll_options' => $options,
                'counts' => [],
                'my_vote_option_id' => null,
                'hearts_count' => 0,
                'my_hearted' => false,
                'user' => ['id' => $post->user->id, 'name' => $post->user->name],
                'created_at' => $post->created_at?->toISOString(),
                'system_payload' => null,
            ];

            DB::afterCommit(fn () => event(new HouseWallPostCreated((int) $user->house_id, $post, $payload)));

            return response()->json(['success' => true, 'post' => $payload], 201);
        });
    }

    public function vote(Request $request, HouseWallPost $post)
    {
        $user = $request->user();
        if (!$user->house_id || (int) $post->house_id !== (int) $user->house_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if ($post->type !== 'poll') {
            return response()->json(['message' => 'Not a poll'], 400);
        }

        $data = $request->validate([
            'option_id' => ['required', 'integer'],
        ]);

        $opt = HouseWallPollOption::where('id', $data['option_id'])
            ->where('post_id', $post->id)
            ->firstOrFail();

        HouseWallPollVote::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $user->id],
            ['option_id' => $opt->id],
        );

        $counts = HouseWallPollVote::query()
            ->selectRaw('option_id, COUNT(*) as c')
            ->where('post_id', $post->id)
            ->groupBy('option_id')
            ->pluck('c', 'option_id')
            ->map(fn ($c) => (int) $c)
            ->all();

        event(new HouseWallPollVoted((int) $user->house_id, (int) $post->id, $counts));

        return response()->json(['success' => true, 'counts' => $counts, 'my_option_id' => $opt->id]);
    }

    public function toggleHeart(Request $request, HouseWallPost $post)
    {
        $user = $request->user();
        if (!$user->house_id || (int) $post->house_id !== (int) $user->house_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $existing = HouseWallReaction::where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        $hearted = false;
        if ($existing) {
            $existing->delete();
            $hearted = false;
        } else {
            HouseWallReaction::create([
                'post_id' => $post->id,
                'user_id' => $user->id,
            ]);
            $hearted = true;
        }

        $count = (int) HouseWallReaction::where('post_id', $post->id)->count();
        event(new HouseWallHeartToggled((int) $user->house_id, (int) $post->id, $count));

        return response()->json(['success' => true, 'hearted' => $hearted, 'hearts_count' => $count]);
    }

    public function getFridgeNote(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) return response()->json(['success' => true, 'note' => null]);

        $row = DB::table('house_wall_fridge_notes')->where('house_id', $user->house_id)->first();
        return response()->json([
            'success' => true,
            'note' => $row ? [
                'body' => $row->body,
                'updated_by' => $row->updated_by,
                'updated_at' => $row->updated_at,
            ] : null,
        ]);
    }

    public function setFridgeNote(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) return response()->json(['message' => 'No house'], 400);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('house_wall_fridge_notes')->updateOrInsert(
            ['house_id' => $user->house_id],
            [
                'body' => $data['body'] ?? null,
                'updated_by' => $user->id,
                'updated_at' => now(),
            ],
        );

        return response()->json(['success' => true]);
    }

    public function getStatuses(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) return response()->json(['success' => true, 'statuses' => []]);

        $rows = DB::table('house_member_statuses')
            ->where('house_id', $user->house_id)
            ->get();

        return response()->json([
            'success' => true,
            'statuses' => $rows->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'status' => $r->status,
                'updated_at' => $r->updated_at,
            ])->values(),
        ]);
    }

    public function setStatus(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) return response()->json(['message' => 'No house'], 400);

        $data = $request->validate([
            'status' => ['required', 'in:home,out,away'],
        ]);

        DB::table('house_member_statuses')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'house_id' => $user->house_id,
                'status' => $data['status'],
                'updated_at' => now(),
            ],
        );

        return response()->json(['success' => true]);
    }
}

