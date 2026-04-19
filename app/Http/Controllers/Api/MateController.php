<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\House;
use App\Models\JoinRequest;
use App\Models\Settlement;
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

        $topLegendId = User::query()
            ->where('house_id', $house->id)
            ->whereIn('status', ['approved', 'admin'])
            ->orderByDesc('karma_balance')
            ->orderBy('created_at')
            ->value('id');

        // Admin object
        $admin = $house->mates()->where('role', 'admin')->first();
        $adminData = $admin ? [
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'is_founder' => (bool) $admin->is_founder,
            'karma_balance' => (int) ($admin->karma_balance ?? 0),
            'is_house_legend' => $topLegendId !== null && (int) $admin->id === (int) $topLegendId,
        ] : null;

        // Approved mates (excluding admin)
        $approved = $house->mates()
            ->where('role', 'mate')
            ->where('status', 'approved')
            ->get(['id', 'name', 'email', 'is_founder', 'karma_balance'])
            ->map(function ($m) use ($topLegendId) {
                return [
                    'id' => $m->id,
                    'name' => $m->name,
                    'email' => $m->email,
                    'is_founder' => (bool) $m->is_founder,
                    'karma_balance' => (int) ($m->karma_balance ?? 0),
                    'is_house_legend' => $topLegendId !== null && (int) $m->id === (int) $topLegendId,
                ];
            })
            ->values()
            ->all();

        // Pending mates
        $pending = $house->joinRequests()
            ->get()
            ->map(function ($req) use ($topLegendId) {
                $user = $req->user; // assuming joinRequest has user relation
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_founder' => (bool) $user->is_founder,
                    'karma_balance' => (int) ($user->karma_balance ?? 0),
                    'is_house_legend' => $topLegendId !== null && (int) $user->id === (int) $topLegendId,
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
            'house_legend_user_id' => $topLegendId ? (int) $topLegendId : null,
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

        if (Settlement::houseUserHasPending((int) $mate->house_id, (int) $mate->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This member has pending settlements. Settle those transfers before removing them.',
            ], 422);
        }

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