<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TripMember extends Pivot
{
    use HasFactory;

    protected $table = 'trip_members';

    protected $fillable = [
        'trip_id',
        'user_id',
        'role',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}