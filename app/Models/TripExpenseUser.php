<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TripExpenseUser extends Pivot
{
    protected $table = 'trip_expense_user';

    protected $fillable = [
        'trip_expense_id',
        'user_id',
        'share_amount',
    ];

    protected $casts = [
        'share_amount' => 'float',
    ];

    public function tripExpense()
    {
        return $this->belongsTo(TripExpense::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
