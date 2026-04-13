<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\House;
use App\Models\JoinRequest;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MateController extends Controller
{
    // Get all mates (approved + pending)
    public function index(Request $request)
    {
        $user = Auth::user();
        $house = $user->house; // assuming User has house() relation
        $isAdmin = $user->role === 'admin';

        if (!$house) {
            return response()->json([
                'approved' => [],
                'pending' => [],
                'is_admin' => $isAdmin,
            ]);
        }

        // Admin object
        $admin = $house->mates()->where('role', 'admin')->first();
        $adminData = $admin ? [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
        ] : null;

        // Approved mates (excluding admin)
        $approved = $house->mates()
            ->where('role', 'mate')
            ->where('status', 'approved')
            ->get(['id', 'name', 'email'])
            ->toArray();

        // Pending mates
        $pending = $house->joinRequests()
            ->get()
            ->map(function ($req) {
                $user = $req->user; // assuming joinRequest has user relation
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });

             $houseData = [
        'id' => $house->id,
        'name' => $house->name,
        'house_code' => $house->code, // unique code for QR
    ];

        return response()->json([
            'is_admin' => $isAdmin,
            'admin' => $adminData,
            'approved' => $approved,
            'pending' => $pending,
            'house' => $houseData,
        ]);
    }

    // Approve a mate
    public function approve(Request $request, $id)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mate = User::findOrFail($id);

        // Assign house + approve
        $mate->house_id = $user->house_id;
        $mate->status = 'approved';
        $mate->role = 'mate';
        $mate->save();

        JoinRequest::where('user_id', $mate->id)
        ->where('house_id', $user->house_id)
        ->delete();

        return response()->json([
            'message' => 'Mate approved',
            'mate' => $mate
        ]);
    }

    // Reject a mate
    public function reject(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mate = User::findOrFail($id);
        $mate->status = 'rejected';
        $mate->save();

        return response()->json(['message' => 'Mate rejected']);
    }

    // Update mate
    public function update(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mate = User::findOrFail($id);
        $mate->name = $request->name ?? $mate->name;
        $mate->save();

        return response()->json(['message' => 'Mate updated', 'mate' => $mate]);
    }

    /**
     * Admin removes another member from the house (same end state as leave-house for that user).
     */
    public function destroy(Request $request, $id)
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$admin->house_id) {
            return response()->json(['message' => 'No house'], 400);
        }

        if ((int) $id === (int) $admin->id) {
            return response()->json([
                'message' => 'Use Leave House in your profile to remove yourself.',
            ], 400);
        }

        $mate = User::findOrFail($id);

        if ((int) $mate->house_id !== (int) $admin->house_id) {
            return response()->json(['message' => 'User is not in your house'], 403);
        }

        $house = House::findOrFail($admin->house_id);

        return DB::transaction(function () use ($house, $mate) {

            // If removing the house admin, promote someone else (same as leaveHouse)
            if ($mate->role === 'admin' || (int) $house->admin_id === (int) $mate->id) {
                $nextUser = User::where('house_id', $house->id)
                    ->where('id', '!=', $mate->id)
                    ->whereIn('status', ['approved', 'admin'])
                    ->first();

                if ($nextUser) {
                    $house->update(['admin_id' => $nextUser->id]);
                    $nextUser->update([
                        'role' => 'admin',
                        'status' => 'admin',
                    ]);
                } else {
                    $house->update(['admin_id' => null]);
                }
            }

            JoinRequest::where('user_id', $mate->id)
                ->where('house_id', $house->id)
                ->delete();

            $mate->update([
                'house_id' => null,
                'role' => 'leave',
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User removed from house',
            ]);
        });
    }
}