<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Google_Client;

class SocialLoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'provider' => 'required|string',
            'access_token' => 'required|string',
        ]);

        if ($request->provider === 'google') {
            return $this->handleGoogle($request->access_token);
        }

        return response()->json([
            'error' => 'Unsupported provider'
        ], 400);
    }

    private function handleGoogle($idToken)
    {
        try {
            $client = new Google_Client([
                'client_id' => env('GOOGLE_CLIENT_ID'),
            ]);

            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return response()->json([
                    'error' => 'Invalid Google token'
                ], 400);
            }

            $email = strtolower($payload['email'] ?? '');
            $name = $payload['name'] ?? 'User';

            if (!$email) {
                return response()->json([
                    'error' => 'Email not available from Google'
                ], 400);
            }

            // 🔍 Check existing user
            $existingUser = User::where('email', $email)->first();

            if ($existingUser) {

                // ❌ Not verified
                if (!$existingUser->email_verified_at) {
                    return response()->json([
                        'error' => 'Please verify your email first'
                    ], 400);
                }

                // ❌ Email/password account → BLOCK
                if (!is_null($existingUser->password)) {
                    return response()->json([
                        'error' => 'Account already exists. Please login with email.'
                    ], 400);
                }

                // ✅ Existing social user
                $user = $existingUser;

            } else {
                // ✅ Create new social user
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => null, // 🔥 IMPORTANT
                    'provider' => 'google',
                    'provider_id' => $payload['sub'],
                    'email_verified_at' => now(),
                    'status' => 'approved',
                ]);
            }

            // 🔐 Token
            $token = $user->createToken('mobile')->plainTextToken;

            return response()->json([
                'success' => true,
                'user' => $user,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Social login failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}