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
        'source',
        'type',
        'title',
        'note',
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

    /**
     * Whether this user appears on any pending settlement row for the house
     * (must settle or confirm before leaving / account deletion).
     */
    public static function houseUserHasPending(int $houseId, int $userId): bool
    {
        return static::query()
            ->where('house_id', $houseId)
            ->where('status', 'pending')
            ->where(function ($q) use ($userId) {
                $q->where('from_user_id', $userId)
                    ->orWhere('to_user_id', $userId);
            })
            ->exists();
    }
}