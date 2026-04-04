<?php

namespace App\Http\Controllers\Api;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TripMemberController extends Controller
{
    /**
     * List all members of a trip
     */
    public function index($tripId)
    {
        $trip = Trip::with('members')->findOrFail($tripId);

        $members = $trip->members->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        });

        return response()->json(['members' => $members]);
    }

    /**
     * Add a user to a trip by email
     */
    public function store(Request $request, $tripId)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $trip = Trip::findOrFail($tripId);
        $user = User::where('email', $request->email)->firstOrFail();

        // Check if email is verified
        if (!$user->email_verified_at) {
            return response()->json(['message' => 'User email is not verified.'], 422);
        }

        // Check if user is already in an active trip
        if ($user->trips()->where('status', 'active')->exists()) {
            return response()->json(['message' => 'User is already in an active trip.'], 422);
        }

        // Check if user already in this trip
        if ($trip->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User is already a member of this trip.'], 409);
        }

        // Attach user to trip
        $trip->members()->attach($user->id);

        return response()->json([
            'message' => 'Member added successfully',
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    /**
     * Remove a member from a trip
     */
    public function destroy($tripId, $userId)
    {
        $trip = Trip::findOrFail($tripId);

        if (!$trip->members()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $trip->members()->detach($userId);

        return response()->json(['message' => 'Member removed successfully']);
    }
}