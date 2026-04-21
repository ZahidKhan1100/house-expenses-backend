<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\Expenses\GetDashboard;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, GetDashboard $action)
    {
        $dashboardData = $action->handle($request->user());
        $house = $request->user()->house;

        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'house_id' => $request->user()->house_id,
                'role' => $request->user()->role,
                'status' => $request->user()->status,
            ],
            'house' => $house ? [
                'id' => $house->id,
                'name' => $house->name,
                'guest_day_weight_percent' => (float) ($house->guest_day_weight_percent ?? 100.0),
            ] : null,
            'total_spent' => $dashboardData['total_spent'],
            'currency' => $dashboardData['currency'],
            'category_expenses' => $dashboardData['categories'],
            'mates' => $dashboardData['mates'],
            'latest_bill' => $dashboardData['latest_bill'] ?? null,
            'split_balance' => $dashboardData['split_balance'] ?? null,
        ]);
    }
}