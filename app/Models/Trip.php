<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'admin_id',
        'currency',
        'start_date',
        'end_date',
        'status',
        'description',
        'budget',
        'location',
        'participants_limit',
    ];

    /**
     * Trip admin (creator)
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Trip members
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'trip_members', 'trip_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * Trip expenses
     */
    public function expenses()
    {
        return $this->hasMany(TripExpense::class);
    }
}