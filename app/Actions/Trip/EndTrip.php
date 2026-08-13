<?php

namespace App\Actions\Trip;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EndTrip
{
    public function execute(Trip $trip, ?string $photoUrl, ?string $photoPublicId): Trip
    {
        $paid = $trip->expenses()->selectRaw('paid_by, SUM(amount) as total')
            ->groupBy('paid_by')->pluck('total', 'paid_by');

        $owed = DB::table('trip_expense_user')
            ->join('trip_expenses', 'trip_expenses.id', '=', 'trip_expense_user.trip_expense_id')
            ->where('trip_expenses.trip_id', $trip->id)
            ->selectRaw('trip_expense_user.user_id, SUM(trip_expense_user.share_amount) as total')
            ->groupBy('trip_expense_user.user_id')
            ->pluck('total', 'user_id');

        $userIds = collect($paid->keys())->merge($owed->keys())->unique();
        $hasOutstandingBalance = $userIds->contains(function ($userId) use ($paid, $owed) {
            $net = (float) ($paid[$userId] ?? 0) - (float) ($owed[$userId] ?? 0);
            return abs($net) > 0.01;
        });

        if ($hasOutstandingBalance) {
            throw ValidationException::withMessages([
                'trip' => 'Trip must be fully settled before it can be ended.',
            ]);
        }

        return DB::transaction(function () use ($trip, $photoUrl, $photoPublicId) {
            $trip->update([
                'status' => 'archived',
                'archived_at' => now(),
                'memory_photo_url' => $photoUrl ?? $trip->memory_photo_url,
                'memory_photo_public_id' => $photoPublicId ?? $trip->memory_photo_public_id,
            ]);

            User::where('trip_id', $trip->id)->update(['trip_id' => null]);

            return $trip;
        });
    }
}
