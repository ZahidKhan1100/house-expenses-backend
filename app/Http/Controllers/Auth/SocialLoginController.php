<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Google_Client;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use App\Actions\Auth\RegisterUser;

class SocialLoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'provider' => 'required|string',
            'access_token' => 'required|string',
            'house_code' => 'nullable|string',
            'mode' => 'nullable|string', // 'house' or 'trip'
            'trip_code' => 'nullable|string',
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
            // ❌ Email/password account → BLOCK
            if (!is_null($existingUser->password)) {
                return response()->json([
                    'error' => 'Account already exists. Please login with email.'
                ], 400);
            }

            $user = $existingUser;
        } else {
            // ✅ Create new social user (minimal data)
            $user = User::create([
                'name' => $userData['name'] ?? 'Social User',
                'email' => strtolower($userData['email']),
                'password' => null,
                'provider' => $provider,
                'provider_id' => $userData['provider_id'] ?? null,
                'email_verified_at' => now(),
                'status' => 'approved',
                'active_mode' => $request->mode ?? 'house',
            ]);
        }

        // ----------------- Handle House / Trip -----------------
        $registerAction = new RegisterUser();
        $result = $registerAction->execute([
            'name' => $user->name,
            'email' => $user->email,
            'houseCode' => $request->house_code ?? null,
            'mode' => $request->mode ?? 'house',
            'trip_code' => $request->trip_code ?? null,
        ]);

        // Ensure token is included for existing user
        if (!isset($result['token'])) {
            $result['token'] = $user->createToken('mobile')->plainTextToken;
        }

        $result['user'] = $user;

        return response()->json($result);
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