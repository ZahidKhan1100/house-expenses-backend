<?php

namespace App\Http\Controllers\Api;

use App\Actions\House\JoinHouseByQRCode;
use App\Http\Controllers\Controller;
use App\Actions\Houses\CreateHouse;
use App\Actions\Houses\JoinHouse;
use App\Http\Requests\CreateHouseRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\House;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HouseController extends Controller
{
    use AuthorizesRequests;
    public function create(CreateHouseRequest $request, CreateHouse $action)
    {
        $house = $action->handle($request->validated(), $request->user());
        return response()->json($house);
    }

    public function join(Request $request, JoinHouse $action)
    {
        $house = $action->handle($request->code, $request->user());
        return response()->json($house);
    }

    public function show(House $house)
    {
        // Load mates and categories
        $house->load('mates', 'categories');

        return response()->json([
            'id' => $house->id,
            'name' => $house->name,
            'currency' => $house->currency ?? '$',
            'mates' => $house->mates->map(fn($mate) => [
                'id' => $mate->id,
                'name' => $mate->name,
                'role' => $mate->role,
            ]),
            'categories' => $house->categories->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'icon' => $cat->icon,
            ]),
        ]);
    }

    public function update(Request $request, $id)
    {
        $house = House::findOrFail($id);

        $this->authorize('update', $house); // optional: ensure user can edit

        $house->update($request->only(['name', 'currency']));
        return response()->json($house);
    }

    public function joinByQRCode(Request $request, JoinHouseByQRCode $action)
    {
        $request->validate([
            'code' => 'required|string|exists:houses,code',
        ]);

        $user = auth()->user();

        $house = $action->execute($user, $request->code);

        return response()->json([
            'message' => 'Joined house successfully',
            'house' => $house
        ]);
    }

    public function current(Request $request)
    {
        $user = $request->user();
        $house = $user->house; // assuming user has house() relation

        if (!$house) {
            return response()->json([
                'house' => null,
            ]);
        }

        return response()->json([
            'house' => [
                'id' => $house->id,
                'name' => $house->name,
                'house_code' => $house->code,
            ]
        ]);
    }

    public function leaveHouse()
    {
        $user = Auth::user();

        if (!$user->house_id) {
            return response()->json(['success' => false, 'message' => 'Not in any house'], 400);
        }

        return DB::transaction(function () use ($user) {
            $house = $user->house;

            if (!$house) {
                return response()->json(['success' => false, 'message' => 'House not found'], 404);
            }

            // Find the oldest remaining approved/admin mate (excluding current user)
            $nextUser = User::where('house_id', $house->id)
                ->where('id', '!=', $user->id)
                ->whereIn('status', ['approved', 'admin'])
                ->orderBy('created_at')
                ->first();

            // 🚪 Remove current user from house first (important: users.house_id has cascadeOnDelete)
            $user->update([
                'house_id' => null,
                'role' => 'mate',
                'status' => 'pending',
            ]);

            // If current user was admin, transfer admin to the oldest remaining mate
            if ($house->admin_id === $user->id) {
                if ($nextUser) {
                    $house->update(['admin_id' => $nextUser->id]);
                    $nextUser->update(['role' => 'admin', 'status' => 'admin']);
                } else {
                    // No mates left -> delete the house
                    $house->delete();
                }
            } else {
                // Non-admin leaving: if no one remains at all, delete the house
                $remainingCount = User::where('house_id', $house->id)->count();
                if ($remainingCount === 0) {
                    $house->delete();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Left house successfully',
            ]);
        });
    }

    public function deleteAccount()
    {
        $user = Auth::user();

        return DB::transaction(function () use ($user) {

            $house = $user->house;

            // -------------------------------
            // 🧠 Handle admin transfer
            // -------------------------------
            if ($house && $house->admin_id === $user->id) {

                $nextUser = User::where('house_id', $house->id)
                    ->where('id', '!=', $user->id)
                    ->whereIn('status', ['approved', 'admin'])
                    ->orderBy('created_at')
                    ->first();

                if ($nextUser) {
                    // ✅ Transfer admin to oldest mate
                    $house->update(['admin_id' => $nextUser->id]);
                    $nextUser->update(['role' => 'admin', 'status' => 'admin']);
                } else {
                    // ✅ No users left -> delete the house
                    // (we must null house_id on the user before deleting to avoid cascade deleting the user unexpectedly)
                    $user->update(['house_id' => null]);
                    $house->delete();
                    $house = null;
                }
            }

            // -------------------------------
            // 🚪 Remove from house + mark deleted
            // -------------------------------
            if ($house && $user->house_id) {
                // ensure user is not tied to house (avoid cascade surprises)
                $user->update(['house_id' => null]);
            }
            $user->delete();

            // -------------------------------
            // 🔐 Logout everywhere
            // -------------------------------
            $user->tokens()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        });
    }

    public function createHouse(Request $request)
    {
        $user = Auth::user();

        if ($user->house_id) {
            return response()->json([
                'message' => 'User already belongs to a house.'
            ], 400);
        }

        // Create the new house
        $house = House::create([
            'name' => $user->name . "'s House",
            'code' => strtoupper(Str::random(6)),
            'admin_id' => $user->id,
            'currency' => '$', // default, can make dynamic later
        ]);

        // Update user role & house
        $user->update([
            'house_id' => $house->id,
            'role' => 'admin',
            'status' => 'admin',
        ]);

        // Default categories
        $defaultCategories = [
            ['name' => 'Grocery', 'icon' => 'shopping-basket'],
            ['name' => 'Rent', 'icon' => 'home'],
        ];

        foreach ($defaultCategories as $cat) {
            $house->categories()->create($cat);
        }

        return response()->json([
            'message' => 'House created successfully',
            'house' => $house
        ], 201);
    }

    public function joinHouse(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'house_code' => 'required|string|exists:houses,code',
        ]);

        $house = House::where('code', $request->house_code)->first();

        if (!$house) {
            return response()->json(['message' => 'House not found'], 404);
        }

        $user->update([
            'house_id' => $house->id,
            'role' => 'mate',
            'status' => 'approved',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Joined house successfully',
            'house' => $house,
            'user' => $user,
        ]);
    }
}