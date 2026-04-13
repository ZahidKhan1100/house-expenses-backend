<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\KarmaService;
use Illuminate\Http\Request;

class KarmaController extends Controller
{
    public function log(Request $request)
    {
        // Internal only: protect with a shared secret header..
        $secret = env('KARMA_LOG_SECRET');
        $header = $request->header('X-Karma-Secret');
        if (!$secret || $header !== $secret) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'points' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:64'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $updated = app(KarmaService::class)->add($user, (int) $data['points'], (string) ($data['reason'] ?? ''));

        return response()->json([
            'success' => true,
            'user_id' => $updated->id,
            'karma_balance' => (int) $updated->karma_balance,
        ]);
    }
}

