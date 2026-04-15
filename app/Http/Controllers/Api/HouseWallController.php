<?php

namespace App\Http\Controllers\Api;

use App\Events\HouseWallHeartToggled;
use App\Events\HouseWallEmojiReacted;
use App\Events\HouseWallPollVoted;
use App\Events\HouseWallPostCreated;
use App\Events\HouseWallRunningLow;
use App\Http\Controllers\Controller;
use App\Models\HouseRunningLowRequest;
use App\Models\HouseWallPollOption;
use App\Models\HouseWallPollVote;
use App\Models\HouseWallPost;
use App\Models\HouseWallReaction;
use App\Models\User;
use App\Services\ExpoPushService;
use App\Services\KarmaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HouseWallController extends Controller
{
    /**
     * @return array<string, array{emoji: string, label: string}>
     */
    private function runningLowCatalog(): array
    {
        return [
            'toilet_paper' => ['emoji' => '🧻', 'label' => 'Toilet paper'],
            'milk' => ['emoji' => '🥛', 'label' => 'Milk'],
            'cooking_oil' => ['emoji' => '🫒', 'label' => 'Cooking oil'],
        ];
    }

    private function normalizeRunningLowLabel(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));

        return mb_strtolower($s);
    }

    private function customRunningLowItemKey(string $normalizedLabel): string
    {
        return 'c_' . substr(sha1($normalizedLabel), 0, 12);
    }

    private function runningLowEmojiForRequest(HouseRunningLowRequest $r, array $cat): string
    {
        if (isset($cat[$r->item_key])) {
            return $cat[$r->item_key]['emoji'];
        }
        if (str_starts_with($r->item_key, 'c_')) {
            return '🛒';
        }

        return '📦';
    }

    public function runningLowList(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) {
            return response()->json(['success' => true, 'items' => [], 'open' => []]);
        }

        $cat = $this->runningLowCatalog();
        $items = [];
        foreach ($cat as $key => $meta) {
            $items[] = [
                'item_key' => $key,
                'emoji' => $meta['emoji'],
                'label' => $meta['label'],
            ];
        }

        $open = HouseRunningLowRequest::query()
            ->where('house_id', $user->house_id)
            ->where('status', 'open')
            ->orderByDesc('id')
            ->get();

        $openPayload = $open->map(function (HouseRunningLowRequest $r) use ($cat) {
            $creator = User::query()->find($r->created_by);
            $label = $r->display_label !== ''
                ? $r->display_label
                : (($cat[$r->item_key]['label'] ?? null) ?: (string) $r->item_key);

            return [
                'id' => $r->id,
                'item_key' => $r->item_key,
                'emoji' => $this->runningLowEmojiForRequest($r, $cat),
                'label' => $label,
                'created_by_name' => $creator?->name ?? 'Someone',
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'items' => $items,
            'open' => $openPayload,
        ]);
    }

    public function runningLowPing(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        $data = $request->validate([
            'item_key' => ['nullable', 'string', 'max:40'],
            'custom_label' => ['nullable', 'string', 'max:48'],
        ]);

        $cat = $this->runningLowCatalog();
        $presetKey = isset($data['item_key']) ? trim((string) $data['item_key']) : '';
        $customRaw = isset($data['custom_label']) ? trim((string) $data['custom_label']) : '';

        if ($presetKey !== '' && $customRaw !== '') {
            return response()->json(['message' => 'Send either item_key or custom_label, not both'], 422);
        }

        if ($presetKey === '' && $customRaw === '') {
            return response()->json(['message' => 'Choose a quick item or enter a custom label'], 422);
        }

        if ($customRaw !== '') {
            $normalized = $this->normalizeRunningLowLabel($customRaw);
            if (mb_strlen($normalized) < 2) {
                return response()->json(['message' => 'Custom label must be at least 2 characters'], 422);
            }
            $itemKey = $this->customRunningLowItemKey($normalized);
            $displayLabel = mb_substr($customRaw, 0, 48);
            $emoji = '🛒';
            $label = $displayLabel;
        } elseif (isset($cat[$presetKey])) {
            $itemKey = $presetKey;
            $displayLabel = $cat[$presetKey]['label'];
            $emoji = $cat[$presetKey]['emoji'];
            $label = $displayLabel;
        } else {
            return response()->json(['message' => 'Unknown quick item'], 422);
        }

        return DB::transaction(function () use ($user, $itemKey, $displayLabel, $emoji, $label) {
            $existing = HouseRunningLowRequest::query()
                ->where('house_id', $user->house_id)
                ->where('item_key', $itemKey)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $req = $existing;
            } else {
                $req = HouseRunningLowRequest::create([
                    'house_id' => $user->house_id,
                    'item_key' => $itemKey,
                    'display_label' => $displayLabel,
                    'status' => 'open',
                    'created_by' => $user->id,
                ]);
            }

            $payload = [
                'id' => $req->id,
                'item_key' => $req->item_key,
                'emoji' => $emoji,
                'label' => $label,
                'created_by' => ['id' => (int) $user->id, 'name' => (string) $user->name],
            ];

            DB::afterCommit(function () use ($user, $payload, $label) {
                event(new HouseWallRunningLow((int) $user->house_id, $payload));

                $push = app(ExpoPushService::class);
                $mates = User::query()
                    ->where('house_id', $user->house_id)
                    ->get(['id', 'expo_push_token']);

                foreach ($mates as $mate) {
                    if ((int) $mate->id === (int) $user->id) {
                        continue;
                    }
                    $token = $mate->expo_push_token ?? '';
                    if ($token === '') {
                        continue;
                    }
                    $push->send(
                        $token,
                        'Running low',
                        'House is low on ' . $label . '! — ' . $user->name,
                        ['type' => 'house.running_low', 'requestId' => $payload['id']],
                    );
                }
            });

            return response()->json(['success' => true, 'request' => $payload]);
        });
    }

    public function uploadSignature(Request $request)
    {
        $user = $request->user();
        if (!$user->house_id) return response()->json(['message' => 'No house'], 400);

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            return response()->json([
                'message' => 'Cloudinary not configured on backend',
            ], 500);
        }

        $timestamp = time();
        $folder = 'habimate/images/' . (int) $user->house_id;

        // Cloudinary signature: sha1(param1=value1&param2=value2...<api_secret>)
        // Params must be sorted by key.
        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];
        ksort($params);
        $toSign = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $signature = sha1($toSign . $apiSecret);

        return response()->json([
            'success' => true,
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'folder' => $folder,
            'signature' => $signature,
        ]);
    }

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

        $emojiCounts = DB::table('house_wall_emoji_reactions')
            ->selectRaw('post_id, emoji, COUNT(*) as c')
            ->whereIn('post_id', $postIds)
            ->groupBy('post_id', 'emoji')
            ->get()
            ->groupBy('post_id')
            ->map(function ($rows) {
                $out = [];
                foreach ($rows as $r) $out[(string) $r->emoji] = (int) $r->c;
                return $out;
            });

        $myEmojis = DB::table('house_wall_emoji_reactions')
            ->whereIn('post_id', $postIds)
            ->where('user_id', $user->id)
            ->get(['post_id', 'emoji'])
            ->groupBy('post_id')
            ->map(fn ($rows) => $rows->pluck('emoji')->values()->all());

        $payload = $posts->map(function (HouseWallPost $p) use ($heartsCounts, $myHearts, $voteCounts, $myVotes, $emojiCounts, $myEmojis) {
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
                'emoji_counts' => $emojiCounts->get($p->id, []),
                'my_emojis' => $myEmojis->get($p->id, []),
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
            'image_public_id' => ['nullable', 'string', 'max:255'],
            'running_low_request_id' => ['nullable', 'integer'],
        ]);

        return DB::transaction(function () use ($user, $data) {
            $lowReq = null;
            if (!empty($data['running_low_request_id'])) {
                $lowReq = HouseRunningLowRequest::query()
                    ->where('id', $data['running_low_request_id'])
                    ->where('house_id', $user->house_id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
            }

            $post = HouseWallPost::create([
                'house_id' => $user->house_id,
                'user_id' => $user->id,
                'type' => 'snippet',
                'caption' => $data['caption'] ?? null,
                'image_url' => $data['image_url'],
                'image_public_id' => $data['image_public_id'] ?? null,
            ])->load('user:id,name');

            $karmaPts = 10;
            $karmaReason = 'wall_contributor';
            if ($lowReq) {
                $lowReq->update([
                    'status' => 'fulfilled',
                    'fulfilled_by' => $user->id,
                    'fulfilled_post_id' => $post->id,
                ]);
                $karmaPts = 20;
                $karmaReason = 'grocery_hero';
            }

            try {
                app(KarmaService::class)->add($user, $karmaPts, $karmaReason);
            } catch (\Throwable $e) {
            }

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
                'emoji_counts' => [],
                'my_emojis' => [],
                'user' => ['id' => $post->user->id, 'name' => $post->user->name],
                'created_at' => $post->created_at?->toISOString(),
                'system_payload' => null,
            ];

            DB::afterCommit(fn () => event(new HouseWallPostCreated((int) $user->house_id, $post, $payload)));

            return response()->json([
                'success' => true,
                'post' => $payload,
                'karma_points' => $karmaPts,
                'karma_reason' => $karmaReason,
            ], 201);
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

            // Karma: Wall Contributor +10
            try {
                app(KarmaService::class)->add($user, 10, 'wall_contributor');
            } catch (\Throwable $e) {
            }

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

    public function toggleEmojiReaction(Request $request, HouseWallPost $post)
    {
        $user = $request->user();
        if (!$user->house_id || (int) $post->house_id !== (int) $user->house_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:16'],
        ]);

        // Allow only a safe, fun shortlist (prevents weird payloads)
        $allowed = ['😂','🥲','🔥','🍕','🧻','🏠','🥳','🤦‍♂️','😴','☕️'];
        if (!in_array($data['emoji'], $allowed, true)) {
            return response()->json(['message' => 'Invalid emoji'], 422);
        }

        $exists = DB::table('house_wall_emoji_reactions')
            ->where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->where('emoji', $data['emoji'])
            ->first();

        $reacted = false;
        if ($exists) {
            DB::table('house_wall_emoji_reactions')
                ->where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->where('emoji', $data['emoji'])
                ->delete();
            $reacted = false;
        } else {
            DB::table('house_wall_emoji_reactions')->insert([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'emoji' => $data['emoji'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $reacted = true;
        }

        $counts = DB::table('house_wall_emoji_reactions')
            ->selectRaw('emoji, COUNT(*) as c')
            ->where('post_id', $post->id)
            ->groupBy('emoji')
            ->pluck('c', 'emoji')
            ->map(fn ($c) => (int) $c)
            ->all();

        event(new HouseWallEmojiReacted((int) $user->house_id, (int) $post->id, $counts));

        return response()->json([
            'success' => true,
            'reacted' => $reacted,
            'emoji' => $data['emoji'],
            'emoji_counts' => $counts,
        ]);
    }

    public function destroy(Request $request, HouseWallPost $post)
    {
        $user = $request->user();
        if (!$user->house_id || (int) $post->house_id !== (int) $user->house_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $isAdmin = ($user->role === 'admin');
        $isOwner = ((int) ($post->user_id ?? 0) === (int) $user->id);

        if (!$isAdmin && !$isOwner) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return DB::transaction(function () use ($post) {
            // Clean up children (no FK constraints in schema)
            DB::table('house_wall_reactions')->where('post_id', $post->id)->delete();
            DB::table('house_wall_emoji_reactions')->where('post_id', $post->id)->delete();
            DB::table('house_wall_poll_votes')->where('post_id', $post->id)->delete();
            DB::table('house_wall_poll_options')->where('post_id', $post->id)->delete();

            $post->delete();

            return response()->json(['success' => true]);
        });
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

