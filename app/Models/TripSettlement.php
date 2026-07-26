<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
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

    public function trip()
    {
        return $this->belongsTo(Trip::class);
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
