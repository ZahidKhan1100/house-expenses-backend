<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\House;
use Google\AccessToken\Verify;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Validation\ValidationException;

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
            return response()->json(['success' => false, 'message' => 'Unsupported provider'], 400);
        }

        if (!$userData || empty($userData['email'])) {
            return response()->json(['success' => false, 'message' => 'Invalid social token'], 400);
        }

        // ----------------- Check existing user -----------------
        $email = strtolower($userData['email']);

        $existingUser = User::withTrashed()->where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->trashed()) {
                $existingUser->restore();
            }

            if ($existingUser->provider !== $provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account already exists. Please login with your original method.',
                ], 400);
            }

            $existingUser->update([
                'provider' => $provider,
                'provider_id' => $userData['provider_id'] ?? $existingUser->provider_id,
                'email_verified_at' => $existingUser->email_verified_at ?? now(),
                'status' => $existingUser->status ?? 'approved',
                'active_mode' => $existingUser->active_mode ?? 'house',
            ]);

            $user = $existingUser;
        } else {
            // ----------------- Create new social user -----------------
            try {
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
            } catch (\Throwable $e) {
                // In case a user exists but wasn't found due to race/edge cases, re-fetch and proceed.
                $user = User::withTrashed()->where('email', $email)->first();
                if (!$user) {
                    throw $e;
                }
                if ($user->trashed()) {
                    $user->restore();
                }
            }

            // Founder is permanent: first 10,000 users by id.
            try {
                if ((int) $user->id <= 10000 && !$user->is_founder) {
                    $user->is_founder = true;
                    $user->save();
                }
            } catch (\Throwable $e) {
            }
        }

        // ----------------- Handle House -----------------
        $houseCode = $request->house_code ?? null;

        if (!$user->house_id) {

            if (!empty($houseCode)) {
                $house = House::where('code', strtoupper($houseCode))->first();
                if (!$house) {
                    throw ValidationException::withMessages([
                        'house_code' => ['That house code isn’t valid. Double-check it or scan the QR again.'],
                    ]);
                }

                $user->update([
                    'house_id' => $house->id,
                    'role' => 'mate',
                    'status' => 'approved',
                    'active_mode' => 'house',
                ]);
            }
            // No house_code: leave user without a house — client shows join vs create (e.g. choose-house).
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
    /**
     * ID tokens are issued with `aud` = the OAuth client that started the sign-in flow
     * (Android / iOS / Web / Expo are different client IDs). Google_Client::verifyIdToken
     * only validates against one audience; we accept any configured client id.
     */
    private function verifyGoogleToken($idToken)
    {
        try {
            $verifier = new Verify(new GuzzleClient());

            // Each platform's id_token has aud = that OAuth client ID. Include every client
            // you use: Android, iOS, Web, and the same value as app extra googleAuth.expoClientId
            // for Expo Go (auth proxy) — typically set GOOGLE_EXPO_CLIENT_ID to that client id.
            $audiences = array_unique(array_filter([
                env('GOOGLE_ANDROID_CLIENT_ID'),
                env('GOOGLE_IOS_CLIENT_ID'),
                env('GOOGLE_WEB_CLIENT_ID'),
                env('GOOGLE_EXPO_CLIENT_ID'),
                env('GOOGLE_CLIENT_ID'),
            ]));

            foreach ($audiences as $audience) {
                $payload = $verifier->verifyIdToken($idToken, $audience);
                if (is_array($payload) && !empty($payload)) {
                    return [
                        'email' => $payload['email'] ?? null,
                        'name' => $payload['name'] ?? 'Google User',
                        'provider_id' => $payload['sub'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }

    // ----------------- Apple Token Verification -----------------
    private function verifyAppleToken($idToken)
    {
        try {
            $appleKeys = json_decode(file_get_contents('https://appleid.apple.com/auth/keys'), true);
            $tokenParts = explode('.', $idToken);
            $header = json_decode(base64_decode($tokenParts[0]), true);
            $key = collect($appleKeys['keys'])->firstWhere('kid', $header['kid']);

            if (!$key)
                return null;

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