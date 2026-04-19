<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserPushToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            // Expo tokens can exceed 255 chars in some cases
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        // Body JSON or X-Client-Platform header (RN always sends both after client update).
        $platformRaw = $validated['platform'] ?? $request->header('X-Client-Platform');
        $platform = is_string($platformRaw) ? strtolower(trim($platformRaw)) : null;
        if ($platform === '') {
            $platform = null;
        }

        UserPushToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $user->id,
                'platform' => $platform,
            ]
        );

        // Keep legacy column as “last registered device” for older clients / debugging
        $user->expo_push_token = $validated['token'];
        $user->save();

        Log::info('Expo push token stored', [
            'user_id' => $user->id,
            'platform' => $platform,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}

