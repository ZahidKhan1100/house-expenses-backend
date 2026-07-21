<?php

namespace App\Actions\Trip;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinTripByCode
{
    public function execute(User $user, string $code): Trip
    {
        $trip = Trip::where('code', strtoupper(trim($code)))->firstOrFail();

        if ($user->trip_id) {
            throw ValidationException::withMessages([
                'trip' => "You're already in an active trip — leave it first.",
            ]);
        }

        DB::transaction(function () use ($user, $trip) {
            $user->trip_id = $trip->id;
            $user->save();

            $trip->members()->syncWithoutDetaching([$user->id]);
        });

        return $trip;
    }
}
