<?php

namespace App\Actions\Expenses;

use App\Events\BillCreated;
use App\Models\Record;
use App\Models\User;
use App\Models\Expense;
use App\Services\ExpenseSplit;
use App\Services\ExpoPushService;
use Illuminate\Support\Facades\DB;

class AddRecord
{
    public function handle(User $user, array $data): Record
    {
        \Log::info('🔥 AddRecord started');
        return DB::transaction(function () use ($user, $data) {

        $month = $data['month'] ?? now()->format('Y-m');
            // Get or create the current expense month
            $expense = Expense::firstOrCreate(
                [
                    'house_id' => $user->house_id,
                    'month' => $month,
                ]
            );

            // Resolve included mates with their names
            $includedMates = [];
            if (!empty($data['included_mates'])) {
                $mates = User::whereIn('id', $data['included_mates'])->get(['id', 'name']);
                foreach ($mates as $mate) {
                    $includedMates[] = [
                        'id' => $mate->id,
                        'name' => $mate->name,
                    ];
                }
            }

            // Get paid_by user
            $paidByUser = User::find($data['paid_by']);

            // Create the record
            $record = Record::create([
                'expense_id' => $expense->id,
                'added_by' => $user->id,
                'added_by_name' => $user->name, // store name
                'description' => $data['description'],
                'amount' => $data['amount'],
                'category_id' => $data['category_id'] ?? null,
                'included_mates' => $includedMates, // store id + name
                'paid_by' => $data['paid_by'],
                'paid_by_name' => $paidByUser?->name ?? 'Unknown',
                'timestamp' => now(),
            ]);

            \Log::info('Expense check', [
                'expense' => $expense
            ]);

            $record = $record->load(['category', 'expense']); // eager load

            // Broadcast + push AFTER commit for consistent cross-device updates
            DB::afterCommit(function () use ($user, $record, $includedMates, $expense) {
                $house = $user->house;
                $currency = $house?->currency ?? '$';

                // Cent-safe share for each participant (id => amount)
                $shares = ExpenseSplit::sharePerUser((float) $record->amount, $includedMates);

                event(new BillCreated(
                    houseId: (int) $user->house_id,
                    record: $record,
                    shares: $shares,
                    currency: $currency,
                ));

                // Expo push: notify every OTHER member with their own share
                $mates = User::where('house_id', $user->house_id)->get(['id', 'name', 'expo_push_token']);
                $push = app(ExpoPushService::class);

                foreach ($mates as $mate) {
                    if ((int) $mate->id === (int) $user->id) continue;
                    $token = $mate->expo_push_token;
                    if (!$token) continue;

                    $share = $shares[(int) $mate->id] ?? null;
                    if ($share === null) continue;

                    $push->send(
                        expoToken: $token,
                        title: 'New bill added',
                        body: $user->name . ' just added ' . $record->description . ' — your share is ' . $currency . number_format((float) $share, 2),
                        data: [
                            'type' => 'bill.created',
                            'billId' => $record->id,
                            'month' => $expense->month,
                        ],
                    );
                }
            });

            return $record; // response body
        });
    }
}