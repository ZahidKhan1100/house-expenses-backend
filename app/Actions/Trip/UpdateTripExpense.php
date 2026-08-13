<?php

namespace App\Actions\Trip;

use App\Models\Trip;
use App\Models\TripExpense;
use App\Services\TripExpenseShareCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTripExpense
{
    public function __construct(private TripExpenseShareCalculator $calculator)
    {
    }

    /**
     * @param  array{title:string,amount:float,currency?:string,notes?:string,split_method:string,participants:array<int,array{user_id:int,value?:float}>}  $data
     */
    public function execute(Trip $trip, TripExpense $expense, int $payerId, array $data): TripExpense
    {
        $amount = (float) $data['amount'];
        $splitMethod = $data['split_method'] ?? 'equal';
        $participants = collect($data['participants'] ?? []);

        $memberIds = $trip->members()->pluck('users.id')->all();
        $invalidIds = $participants->pluck('user_id')->diff($memberIds);
        if ($invalidIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'participants' => 'All participants must be members of this trip.',
            ]);
        }

        $shares = $this->calculator->compute($splitMethod, $amount, $participants);

        return DB::transaction(function () use ($expense, $payerId, $data, $shares) {
            $expense->update([
                'paid_by' => $payerId,
                'title' => $data['title'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? $expense->currency,
                'notes' => $data['notes'] ?? null,
                'split_method' => $data['split_method'] ?? 'equal',
            ]);

            DB::table('trip_expense_user')->where('trip_expense_id', $expense->id)->delete();

            $rows = [];
            foreach ($shares as $userId => $shareAmount) {
                $rows[] = [
                    'trip_expense_id' => $expense->id,
                    'user_id' => $userId,
                    'share_amount' => $shareAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('trip_expense_user')->insert($rows);

            return $expense->load('participants', 'payer');
        });
    }
}
