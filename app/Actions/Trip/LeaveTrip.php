<?php

namespace App\Actions\Trip;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveTrip
{
    public function execute(User $user): void
    {
        if (! $user->trip_id) {
            throw ValidationException::withMessages([
                'trip' => "You're not currently in a trip.",
            ]);
        }

        DB::transaction(function () use ($user) {
            $tripId = $user->trip_id;

            $user->trip_id = null;
            $user->save();

            $user->trips()->detach($tripId);
        });
    }
}
