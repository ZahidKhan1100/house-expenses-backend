<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'house_id',
        'month',
        'from_user_id',
        'to_user_id',
        'from_name',
        'to_name',
        'amount',
        'status',
        'settled_at',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}