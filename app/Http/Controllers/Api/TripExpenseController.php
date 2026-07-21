<?php

namespace App\Http\Controllers\Api;

use App\Actions\Trip\AddTripExpense;
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

        return response()->json(['expenses' => $expenses]);
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
        ]);

        $expense = $action->execute($trip, Auth::user(), $data);

        return response()->json(['success' => true, 'expense' => $expense], 201);
    }

    public function show($tripId, $expenseId)
    {
        $trip = $this->authorizedTrip($tripId);

        $expense = $trip->expenses()->with('participants', 'payer')->findOrFail($expenseId);

        return response()->json(['expense' => $expense]);
    }

    public function update(Request $request, $tripId, $expenseId)
    {
        $trip = $this->authorizedTrip($tripId);
        $expense = $trip->expenses()->findOrFail($expenseId);

        $this->authorizeExpenseEdit($trip, $expense);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $expense->update($data);

        return response()->json(['success' => true, 'expense' => $expense->load('participants', 'payer')]);
    }

    public function destroy($tripId, $expenseId)
    {
        $trip = $this->authorizedTrip($tripId);
        $expense = $trip->expenses()->findOrFail($expenseId);

        $this->authorizeExpenseEdit($trip, $expense);

        $expense->delete();

        return response()->json(['success' => true, 'message' => 'Expense deleted']);
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
