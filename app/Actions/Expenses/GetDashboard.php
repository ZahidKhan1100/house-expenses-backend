<?php

namespace App\Actions\Expenses;

use App\Models\User;

class GetDashboard
{
    public function handle(User $user): array
    {
        $house = $user->house;

        if (!$house) {
            return [
                'total_spent' => 0,
                'currency' => '$',
                'categories' => [],
                'mates' => [],
                'month' => now()->format('Y-m'),
            ];
        }

        // ✅ CURRENT MONTH (you can later pass from frontend if needed)
        $month = now()->format('Y-m');

        // ✅ Get ONLY current month expense
        $expenseMonth = $house->expenses()
            ->where('month', $month)
            ->with(['records.category'])
            ->first();

        $records = $expenseMonth?->records ?? collect();

        // --- Categories ---
        $categories = $house->categories()
            ->get()
            ->mapWithKeys(function ($cat) {
                return [
                    $cat->id => [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'icon' => $cat->icon,
                        'total' => 0,
                    ]
                ];
            })
            ->toArray();

        $totalSpent = 0;

        // --- Process only current month records ---
        foreach ($records as $record) {

            $totalSpent += $record->amount;

            if ($record->category_id && isset($categories[$record->category_id])) {
                $categories[$record->category_id]['total'] += $record->amount;
            }
        }

        // --- Mates ---
        $mates = $house->mates()
            ->get()
            ->map(function ($mate) {
                return [
                    'id' => $mate->id,
                    'name' => $mate->name,
                    'role' => $mate->role,
                ];
            });

        return [
            'total_spent' => round($totalSpent, 2),
            'currency' => $house->currency ?? '$',
            'categories' => array_values($categories),
            'mates' => $mates,
            'month' => $month,
        ];
    }
}