<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Auth;
use App\Models\Trip;

class LoginUser
{
    public function execute(array $data)
    {
        // Attempt login
        if (!Auth::attempt($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        $user = auth()->user();

        // Check email verification
        if (!$user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email first',
                'email_verified' => false,
            ], 403);
        }

        // Create token
        $token = $user->createToken('mobile')->plainTextToken;

        // Prepare trip data if active_mode is trip
        $trip = null;
        if ($user->active_mode === 'trip' && $user->trip_id) {
            $trip = Trip::find($user->trip_id);

            // Only return trip if active and has dates
            if ($trip && ($trip->status !== 'active' || !$trip->start_date || !$trip->end_date)) {
                $trip = null; // incomplete trip
            }
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user,
            'active_trip' => $trip, // null if not ready
        ], 200);
    }
}