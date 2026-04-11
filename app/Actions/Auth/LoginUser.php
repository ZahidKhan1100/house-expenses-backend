<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Auth;

class LoginUser
{
    public function execute(array $data)
    {
        // =====================================================
        // 🔐 Attempt login
        // =====================================================
        if (!Auth::attempt($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        $user = auth()->user();

        // =====================================================
        // 🗑️ BLOCK SOFT-DELETED USERS SAFELY
        // =====================================================
        if ($user->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Account deleted. Please sign up again to restore it.',
            ], 403);
        }

        // =====================================================
        // 📧 EMAIL VERIFICATION CHECK
        // =====================================================
        if (!$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email first',
                'email_verified' => false,
            ], 403);
        }

        // =====================================================
        // 🔐 CREATE TOKEN
        // =====================================================
        $token = $user->createToken('mobile')->plainTextToken;

        // =====================================================
        // 🏠 LOAD HOUSE DATA (ONLY SYSTEM NOW)
        // =====================================================
        $house = null;

        if ($user->house_id) {
            $house = $user->house()->with(['categories'])->first();
        }

        // =====================================================
        // RESPONSE
        // =====================================================
        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
            'house' => $house,
        ], 200);
    }
}