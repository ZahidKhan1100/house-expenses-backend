<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\House;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Google_Client;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class SocialLoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'provider' => 'required|string',
            'access_token' => 'required|string',
            'house_code' => 'nullable|string',
            'mode' => 'nullable|string', // 'house'
        ]);

        $provider = $request->provider;
        $token = $request->access_token;

        // ----------------- Verify social token -----------------
        if ($provider === 'google') {
            $userData = $this->verifyGoogleToken($token);
        } elseif ($provider === 'apple') {
            $userData = $this->verifyAppleToken($token);
        } else {
            return response()->json(['error' => 'Unsupported provider'], 400);
        }

        if (!$userData || empty($userData['email'])) {
            return response()->json(['error' => 'Invalid social token'], 400);
        }

        // ----------------- Check existing user -----------------
        $existingUser = User::where('email', $userData['email'])->first();

        if ($existingUser) {
            if ($existingUser->provider !== $provider) {
                return response()->json([
                    'error' => 'Account already exists. Please login with your original method.'
                ], 400);
            }

            $user = $existingUser;
        } else {
            // ----------------- Create new social user -----------------
            $user = User::create([
                'name' => $userData['name'] ?? 'Social User',
                'email' => strtolower($userData['email']),
                'password' => null,
                'provider' => $provider,
                'provider_id' => $userData['provider_id'] ?? null,
                'email_verified_at' => now(),
                'status' => 'approved',
                'active_mode' => 'house',
            ]);
        }

        // ----------------- Handle House -----------------
        $houseCode = $request->house_code ?? null;

        if (!empty($houseCode)) {
            $house = House::where('code', strtoupper($houseCode))->firstOrFail();
            $user->update([
                'house_id' => $house->id,
                'role' => 'mate',
                'status' => 'approved',
                'active_mode' => 'house',
            ]);
        } else {
            // Create new house for user
            $house = House::create([
                'name' => $user->name . "'s House",
                'code' => strtoupper(Str::random(6)),
                'admin_id' => $user->id,
                'currency' => '$',
            ]);

            $user->update([
                'house_id' => $house->id,
                'role' => 'admin',
                'status' => 'admin',
                'active_mode' => 'house',
            ]);

            // Default categories
            $defaultCategories = [
                ['name' => 'Grocery', 'icon' => 'shopping-basket'],
                ['name' => 'Rent', 'icon' => 'home'],
            ];

            foreach ($defaultCategories as $cat) {
                $house->categories()->create($cat);
            }
        }

        // ----------------- Generate Token -----------------
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'mode' => 'house',
            'token' => $token,
            'user' => $user,
            'email_verified' => !is_null($user->email_verified_at),
        ]);
    }

    // ----------------- Google Token Verification -----------------
    private function verifyGoogleToken($idToken)
    {
        try {
            $client = new Google_Client();
            $payload = $client->verifyIdToken($idToken, [
                env('GOOGLE_CLIENT_ID'),
                env('GOOGLE_IOS_CLIENT_ID'),
                env('GOOGLE_ANDROID_CLIENT_ID'),
                env('GOOGLE_WEB_CLIENT_ID'),
            ]);

            if (!$payload) return null;

            return [
                'email' => $payload['email'] ?? null,
                'name' => $payload['name'] ?? 'Google User',
                'provider_id' => $payload['sub'] ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    // ----------------- Apple Token Verification -----------------
    private function verifyAppleToken($idToken)
    {
        try {
            $appleKeys = json_decode(file_get_contents('https://appleid.apple.com/auth/keys'), true);
            $tokenParts = explode('.', $idToken);
            $header = json_decode(base64_decode($tokenParts[0]), true);
            $key = collect($appleKeys['keys'])->firstWhere('kid', $header['kid']);

            if (!$key) return null;

            $publicKey = JWK::parseKey($key);
            $payload = JWT::decode($idToken, $publicKey);

            return [
                'email' => strtolower($payload->email ?? null),
                'name' => 'Apple User',
                'provider_id' => $payload->sub ?? null,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}