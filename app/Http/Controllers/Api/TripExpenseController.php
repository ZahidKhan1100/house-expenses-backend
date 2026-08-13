<?php

namespace App\Http\Controllers\Api;

use App\Actions\Trip\AddTripExpense;
use App\Actions\Trip\UpdateTripExpense;
use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\TripExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TripExpenseController extends Controller
{
    public function index($tripId)
    {
        $trip = $this->authorizedTrip($tripId);

        $expenses = $trip->expenses()->with('participants', 'payer')->latest()->get();

        return response()->json(['expenses' => $expenses->map($this->formatExpense(...))]);
    }

    public function store(Request $request, $tripId, AddTripExpense $action)
    {
        $trip = $this->authorizedTrip($tripId);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:5',
            'notes' => 'nullable|string|max:1000',
            'split_method' => 'required|in:equal,percentage,exact',
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|integer',
            'participants.*.value' => 'nullable|numeric',
            'paid_by' => 'nullable|integer',
        ]);

        $payerId = $this->resolvePayerId($trip, $data['paid_by'] ?? null);
        $expense = $action->execute($trip, $payerId, $data);

        return response()->json(['success' => true, 'expense' => $this->formatExpense($expense)], 201);
    }

    public function show($tripId, $expenseId)
    {
        $trip = $this->authorizedTrip($tripId);

        $expense = $trip->expenses()->with('participants', 'payer')->findOrFail($expenseId);

        return response()->json(['expense' => $this->formatExpense($expense)]);
    }

    public function update(Request $request, $tripId, $expenseId, UpdateTripExpense $action)
    {
        $trip = $this->authorizedTrip($tripId);
        $expense = $trip->expenses()->findOrFail($expenseId);

        $this->authorizeExpenseEdit($trip, $expense);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:5',
            'notes' => 'nullable|string|max:1000',
            'split_method' => 'required|in:equal,percentage,exact',
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'required|integer',
            'participants.*.value' => 'nullable|numeric',
            'paid_by' => 'nullable|integer',
        ]);

        $payerId = $this->resolvePayerId($trip, $data['paid_by'] ?? null);
        $expense = $action->execute($trip, $expense, $payerId, $data);

        return response()->json(['success' => true, 'expense' => $this->formatExpense($expense)]);
    }

    public function destroy($tripId, $expenseId)
    {
        $trip = $this->authorizedTrip($tripId);
        $expense = $trip->expenses()->findOrFail($expenseId);

        $this->authorizeExpenseEdit($trip, $expense);

        $expense->delete();

        return response()->json(['success' => true, 'message' => 'Expense deleted']);
    }

    /**
     * Strip house-scoped and other internal user fields from expense payer/participants
     * before serialization, matching the id/name/email whitelist used in TripMemberController.
     */
    private function formatExpense(TripExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'trip_id' => $expense->trip_id,
            'title' => $expense->title,
            'amount' => $expense->amount,
            'currency' => $expense->currency,
            'notes' => $expense->notes,
            'split_method' => $expense->split_method,
            'paid_by' => $expense->paid_by,
            'created_at' => $expense->created_at,
            'updated_at' => $expense->updated_at,
            'payer' => $expense->payer ? [
                'id' => $expense->payer->id,
                'name' => $expense->payer->name,
                'email' => $expense->payer->email,
            ] : null,
            'participants' => $expense->participants->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'share_amount' => $user->pivot->share_amount,
            ]),
        ];
    }

    private function resolvePayerId(Trip $trip, ?int $requestedPayerId): int
    {
        if ($requestedPayerId === null) {
            return Auth::id();
        }

        $isMember = (int) $trip->admin_id === $requestedPayerId || $trip->members()->where('users.id', $requestedPayerId)->exists();
        abort_unless($isMember, 422, 'The selected payer must be a member of this trip.');

        return $requestedPayerId;
    }

    private function authorizedTrip($tripId): Trip
    {
        $trip = Trip::findOrFail($tripId);

        $isMember = (int) $trip->admin_id === Auth::id() || $trip->members()->where('users.id', Auth::id())->exists();
        abort_unless($isMember, 403, 'You are not a member of this trip.');

        return $trip;
    }

    private function authorizeExpenseEdit(Trip $trip, TripExpense $expense): void
    {
        $canEdit = (int) $trip->admin_id === Auth::id() || (int) $expense->paid_by === Auth::id();
        abort_unless($canEdit, 403, 'Only the trip admin or the payer can modify this expense.');
    }
}
