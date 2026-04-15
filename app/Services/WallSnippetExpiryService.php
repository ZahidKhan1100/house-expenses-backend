<?php

namespace App\Services;

use App\Models\HouseWallPost;
use Illuminate\Support\Facades\DB;

class WallSnippetExpiryService
{
    public function cleanupExpiredSnippets(?int $days = null): int
    {
        $ttlDays = $days ?? (int) (env('WALL_SNIPPET_TTL_DAYS', 2));
        if ($ttlDays <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($ttlDays);

        $posts = HouseWallPost::query()
            ->where('type', 'snippet')
            ->whereNotNull('image_url')
            ->where('created_at', '<', $cutoff)
            ->limit(250)
            ->get(['id', 'image_public_id']);

        if ($posts->isEmpty()) {
            return 0;
        }

        $cloud = app(CloudinaryService::class);
        $deleted = 0;

        foreach ($posts as $p) {
            DB::transaction(function () use ($p, $cloud, &$deleted) {
                // Delete Cloudinary asset best-effort
                try {
                    if ($p->image_public_id) {
                        $cloud->deleteImageByPublicId((string) $p->image_public_id);
                    }
                } catch (\Throwable $e) {
                }

                // Remove children (no FKs)
                DB::table('house_wall_reactions')->where('post_id', $p->id)->delete();
                DB::table('house_wall_emoji_reactions')->where('post_id', $p->id)->delete();
                DB::table('house_wall_poll_votes')->where('post_id', $p->id)->delete();
                DB::table('house_wall_poll_options')->where('post_id', $p->id)->delete();

                HouseWallPost::where('id', $p->id)->delete();
                $deleted++;
            });
        }

        return $deleted;
    }
}

