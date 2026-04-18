<?php

namespace App\Services;

use App\Events\HouseWallPostCreated;
use App\Models\HouseCalendarBlock;
use App\Models\HouseWallPost;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HouseCalendarAnnouncementService
{
    public function announceBlock(User $actor, HouseCalendarBlock $block, ?string $reasonEmoji): void
    {
        $mate = User::query()->find((int) $block->user_id);
        if (! $mate) {
            return;
        }

        $houseId = (int) $block->house_id;
        $start = Carbon::parse($block->starts_on)->format('M j');
        $end = Carbon::parse($block->ends_on)->format('M j');
        $tag = $reasonEmoji ? trim($reasonEmoji).' ' : '';

        if ($block->kind === 'guest') {
            $caption = "{$tag}{$mate->name}: +1 guest {$start}–{$end}. Utilities weighted.";
        } else {
            $caption = "{$tag}{$mate->name} away {$start}–{$end}. Variable splits adjust.";
        }

        if (mb_strlen($caption) > 100) {
            $caption = mb_substr($caption, 0, 97).'…';
        }

        $kind = $block->kind === 'guest' ? 'guest_stay' : 'vacation_alert';

        DB::transaction(function () use ($houseId, $mate, $caption, $kind, $block, $reasonEmoji) {
            $post = HouseWallPost::create([
                'house_id' => $houseId,
                'user_id' => (int) $mate->id,
                'type' => 'system',
                'caption' => $caption,
                'system_payload' => [
                    'kind' => $kind,
                    'block_id' => (int) $block->id,
                    'subject_user_id' => (int) $mate->id,
                    'starts_on' => $block->starts_on->format('Y-m-d'),
                    'ends_on' => $block->ends_on->format('Y-m-d'),
                    'reason_emoji' => $reasonEmoji,
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
                'user' => ['id' => (int) $mate->id, 'name' => (string) $mate->name],
                'created_at' => $post->created_at?->toISOString(),
                'system_payload' => $post->system_payload,
            ];

            DB::afterCommit(fn () => event(new HouseWallPostCreated($houseId, $post, $payload)));
        });

        $title = $block->kind === 'guest'
            ? 'Guest stay on the Wall'
            : 'Trip on the Wall';

        $body = "{$mate->name} · {$start}–{$end}. HabiMate updated split logic.";

        User::query()
            ->where('house_id', $houseId)
            ->where('id', '!=', (int) $actor->id)
            ->get()
            ->each(function (User $u) use ($title, $body, $houseId) {
                app(ExpoPushService::class)->sendToUserDevices($u, $title, $body, [
                    'type' => 'house_calendar',
                    'house_id' => (string) $houseId,
                ]);
            });
    }
}
