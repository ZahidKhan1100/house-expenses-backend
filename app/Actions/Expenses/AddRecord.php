<?php

namespace App\Actions\Expenses;

use App\Events\BillCreated;
use App\Models\Record;
use App\Models\User;
use App\Models\Expense;
use App\Services\ExpenseSplit;
use App\Services\ExpoPushService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddRecord
{
    public function handle(User $user, array $data): Record
    {
        \Log::info('🔥 AddRecord started');
        return DB::transaction(function () use ($user, $data) {

        $month = $data['month'] ?? now()->format('Y-m');
            $splitMethod = $data['split_method'] ?? 'equal';
            $excludedByUser = is_array($data['excluded_days_by_user'] ?? null)
                ? $data['excluded_days_by_user']
                : [];
            $guestExtraByUser = is_array($data['guest_extra_days_by_user'] ?? null)
                ? $data['guest_extra_days_by_user']
                : [];

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

            $billPeriodDays = null;
            if ($splitMethod === 'days') {
                try {
                    $billPeriodDays = Carbon::createFromFormat('Y-m', $month)->daysInMonth;
                } catch (\Throwable) {
                    $billPeriodDays = null;
                }
            }

            // Create the record
            $record = Record::create([
                'expense_id' => $expense->id,
                'added_by' => $user->id,
                'added_by_name' => $user->name, // store name
                'description' => $data['description'],
                'amount' => $data['amount'],
                'category_id' => $data['category_id'] ?? null,
                'included_mates' => $includedMates, // store id + name
                'split_method' => $splitMethod,
                'bill_period_days' => $billPeriodDays,
                'paid_by' => $data['paid_by'],
                'paid_by_name' => $paidByUser?->name ?? 'Unknown',
                'timestamp' => now(),
            ]);

            // Persist per-user excluded days (best-effort; keeps feature optional).
            try {
                $rows = [];
                foreach ($includedMates as $mate) {
                    $uid = (int) ($mate['id'] ?? 0);
                    if (!$uid) continue;
                    $ex = (int) ($excludedByUser[$uid] ?? $excludedByUser[(string) $uid] ?? 0);
                    if ($ex < 0) $ex = 0;
                    $gx = (int) ($guestExtraByUser[$uid] ?? $guestExtraByUser[(string) $uid] ?? 0);
                    if ($gx < 0) $gx = 0;
                    $rows[] = [
                        'record_id' => (int) $record->id,
                        'user_id' => $uid,
                        'excluded_days' => $ex,
                        'guest_extra_days' => $gx,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if (!empty($rows)) {
                    DB::table('record_user')->upsert(
                        $rows,
                        ['record_id', 'user_id'],
                        ['excluded_days', 'guest_extra_days', 'updated_at']
                    );
                }
            } catch (\Throwable) {
                // ignore
            }

            \Log::info('Expense check', [
                'expense' => $expense
            ]);

            $record = $record->load(['category', 'expense']); // eager load

            // Broadcast + push AFTER commit for consistent cross-device updates
            DB::afterCommit(function () use ($user, $record, $includedMates, $expense) {
                $house = $user->house;
                $currency = $house?->currency ?? '$';

                $fresh = Record::find($record->id);

                // Cent-safe share for each participant (id => amount)
                if (($fresh->split_method ?? 'equal') === 'days') {
                    $excluded = is_array($fresh->excluded_days_by_user ?? null) ? $fresh->excluded_days_by_user : [];
                    $guestExtra = is_array($fresh->guest_extra_days_by_user ?? null) ? $fresh->guest_extra_days_by_user : [];
                    $billDays = (int) ($fresh->bill_period_days ?? 0);
                    $weighted = array_map(static function ($m) use ($excluded, $guestExtra, $billDays) {
                        $id = (int) ($m['id'] ?? 0);
                        $ex = (int) ($excluded[$id] ?? 0);
                        if ($ex < 0) $ex = 0;
                        $gx = (int) ($guestExtra[$id] ?? 0);
                        if ($gx < 0) $gx = 0;
                        $eff = max(0, $billDays - $ex) + $gx;
                        return ['id' => $id, 'weight' => $eff];
                    }, $includedMates);
                    $shares = ExpenseSplit::sharePerUserWeighted((float) $fresh->amount, $weighted);
                } else {
                    $shares = ExpenseSplit::sharePerUser((float) $fresh->amount, $includedMates);
                }

                event(new BillCreated(
                    houseId: (int) $user->house_id,
                    record: $fresh,
                    shares: $shares,
                    currency: $currency,
                ));

                // Expo push: notify every OTHER member (all their devices: iOS + Android)
                $mates = User::where('house_id', $user->house_id)
                    ->with('pushTokens')
                    ->get(['id', 'name', 'expo_push_token']);
                $push = app(ExpoPushService::class);

                foreach ($mates as $mate) {
                    if ((int) $mate->id === (int) $user->id) {
                        continue;
                    }

                    $share = $shares[(int) $mate->id] ?? null;
                    if ($share === null) {
                        continue;
                    }

                    if ($mate->allExpoPushTokens()->isEmpty()) {
                        Log::info('Push skipped (no expo token)', [
                            'type' => 'bill.created',
                            'to_user_id' => (int) $mate->id,
                            'to_user_name' => (string) ($mate->name ?? ''),
                            'house_id' => (int) $user->house_id,
                        ]);

                        continue;
                    }

                    Log::info('Sending push', [
                        'type' => 'bill.created',
                        'to_user_id' => (int) $mate->id,
                        'house_id' => (int) $user->house_id,
                        'bill_id' => (int) $fresh->id,
                    ]);
                    $push->sendToUserDevices(
                        $mate,
                        'New bill added',
                        $user->name . ' just added ' . $fresh->description . ' — your share is ' . $currency . number_format((float) $share, 2),
                        [
                            'type' => 'bill.created',
                            'billId' => $fresh->id,
                            'month' => $expense->month,
                        ],
                    );
                }
            });

            return $record; // response body
        });
    }
}