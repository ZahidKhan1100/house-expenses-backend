<?php

namespace App\Http\Controllers\Api;

use App\Models\Trip;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTripRequest;
use App\Actions\Trip\CreateTrip;
use App\Actions\Trip\EndTrip;
use App\Actions\Trip\JoinTripByCode;
use App\Actions\Trip\LeaveTrip;
use App\Services\SettlementEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TripController extends Controller
{
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $trips = Trip::where('admin_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->get();

        return response()->json([
            'success' => true,
            'trips' => $trips,
            'current_trip_id' => $user->trip_id,
        ]);
    }

    public function store(CreateTripRequest $request, CreateTrip $createTrip): JsonResponse
    {
        $user = $request->user();

        if ($user->trip_id) {
            return response()->json([
                'success' => false,
                'message' => "You're already in an active trip — leave it first.",
            ], 422);
        }

        $trip = DB::transaction(function () use ($request, $createTrip, $user) {
            $trip = $createTrip->execute($request->validated(), $user->id);

            $user->trip_id = $trip->id;
            $user->save();
            $trip->members()->syncWithoutDetaching([$user->id]);

            return $trip;
        });

        return response()->json([
            'success' => true,
            'trip' => $trip,
        ], 201);
    }

    public function show($tripId): JsonResponse
    {
        $trip = $this->authorizedTrip($tripId);

        return response()->json([
            'success' => true,
            'trip' => $trip,
        ]);
    }

    public function update(CreateTripRequest $request, $tripId): JsonResponse
    {
        $trip = Trip::where('id', $tripId)
            ->where('admin_id', Auth::id())
            ->firstOrFail();

        $this->assertTripActive($trip);

        $trip->update($request->validated());

        return response()->json([
            'success' => true,
            'trip' => $trip,
        ]);
    }

    public function end(Request $request, $tripId, EndTrip $action): JsonResponse
    {
        $trip = Trip::where('id', $tripId)
            ->where('admin_id', Auth::id())
            ->firstOrFail();

        $this->assertTripActive($trip);

        $data = $request->validate([
            'photo_url' => 'nullable|string|max:2048',
            'photo_public_id' => 'nullable|string|max:255',
        ]);

        $trip = $action->execute($trip, $data['photo_url'] ?? null, $data['photo_public_id'] ?? null);

        return response()->json([
            'success' => true,
            'trip' => $trip,
        ]);
    }

    public function updatePhoto(Request $request, $tripId): JsonResponse
    {
        $trip = Trip::where('id', $tripId)
            ->where('admin_id', Auth::id())
            ->firstOrFail();

        $data = $request->validate([
            'photo_url' => 'nullable|string|max:2048',
            'photo_public_id' => 'nullable|string|max:255',
        ]);

        $trip->update([
            'memory_photo_url' => $data['photo_url'] ?? null,
            'memory_photo_public_id' => $data['photo_public_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'trip' => $trip,
        ]);
    }

    public function uploadSignature(Request $request, $tripId): JsonResponse
    {
        $trip = Trip::where('id', $tripId)
            ->where('admin_id', Auth::id())
            ->firstOrFail();

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            return response()->json([
                'message' => 'Cloudinary not configured on backend',
            ], 500);
        }

        $timestamp = time();
        $folder = 'habimate/images/trips/' . (int) $trip->id;

        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];
        ksort($params);
        $toSign = urldecode(http_build_query($params));
        $signature = sha1($toSign . $apiSecret);

        return response()->json([
            'success' => true,
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'folder' => $folder,
            'signature' => $signature,
        ]);
    }

    public function destroy($tripId): JsonResponse
    {
        $trip = Trip::where('id', $tripId)
            ->where('admin_id', Auth::id())
            ->firstOrFail();

        DB::transaction(function () use ($trip) {
            \App\Models\User::where('trip_id', $trip->id)->update(['trip_id' => null]);
            $trip->delete();
        });

        return response()->json(['success' => true, 'message' => 'Trip deleted']);
    }

    public function join(Request $request, JoinTripByCode $action): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $trip = $action->execute(Auth::user(), $request->code);

        return response()->json([
            'success' => true,
            'message' => 'Joined trip successfully',
            'trip' => $trip,
        ]);
    }

    public function leave(LeaveTrip $action): JsonResponse
    {
        $action->execute(Auth::user());

        return response()->json(['success' => true, 'message' => 'Left trip successfully']);
    }

    public function balances($tripId, SettlementEngine $engine): JsonResponse
    {
        $trip = $this->authorizedTrip($tripId);

        $paid = $trip->expenses()->selectRaw('paid_by, SUM(amount) as total')
            ->groupBy('paid_by')->pluck('total', 'paid_by');

        $owed = DB::table('trip_expense_user')
            ->join('trip_expenses', 'trip_expenses.id', '=', 'trip_expense_user.trip_expense_id')
            ->where('trip_expenses.trip_id', $trip->id)
            ->selectRaw('trip_expense_user.user_id, SUM(trip_expense_user.share_amount) as total')
            ->groupBy('trip_expense_user.user_id')
            ->pluck('total', 'user_id');

        $userIds = collect($paid->keys())->merge($owed->keys())->unique();
        $balances = $userIds->mapWithKeys(function ($userId) use ($paid, $owed) {
            $net = (float) ($paid[$userId] ?? 0) - (float) ($owed[$userId] ?? 0);
            return [$userId => $net];
        })->all();

        $transactions = $engine->optimize($balances);

        return response()->json([
            'success' => true,
            'net_balances' => $balances,
            'transactions' => $transactions,
        ]);
    }

    private function authorizedTrip($tripId): Trip
    {
        $trip = Trip::findOrFail($tripId);

        $isMember = (int) $trip->admin_id === Auth::id() || $trip->members()->where('users.id', Auth::id())->exists();
        abort_unless($isMember, 403, 'You are not a member of this trip.');

        return $trip;
    }

    private function assertTripActive(Trip $trip): void
    {
        abort_if($trip->status === 'archived', 422, 'This trip has ended and can no longer be modified.');
    }
}
