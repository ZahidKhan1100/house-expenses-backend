<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'paid_by',
        'title',
        'amount',
        'currency',
        'notes',
    ];

    /**
     * Trip this expense belongs to
     */
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * User who paid the expense
     */
    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}