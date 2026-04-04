<?php

namespace App\Http\Controllers\Api;

use App\Models\Trip;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateTripRequest;
use App\Actions\Trip\CreateTrip;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TripController extends Controller
{
    //
    public function store(CreateTripRequest $request, CreateTrip $createTrip): JsonResponse
    {
        $user = $request->user();

        $trip = $createTrip->execute($request->validated(), $user->id);

        return response()->json([
            'success' => true,
            'trip' => $trip,
        ], 201);
    }

    public function show($tripId): JsonResponse
    {
        $trip = Trip::where('id', $tripId)
            ->where('admin_id', Auth::id())
            ->firstOrFail();

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

        $trip->update($request->validated());

        return response()->json([
            'success' => true,
            'trip' => $trip,
        ]);
    }
}
