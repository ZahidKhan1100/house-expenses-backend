<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable, SoftDeletes;


    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'house_id',
        'provider',
        'provider_id',
        'email_verification_token',
        'email_verified_at',
        'active_mode',
        'expo_push_token',
        'trip_id',
    ];

    protected $hidden = ['password'];

    public function house()
    {
        return $this->belongsTo(House::class);
    }

    public function records()
    {
        return $this->hasMany(Record::class, 'added_by');
    }

    public function paidRecords()
    {
        return $this->hasMany(Record::class, 'paid_by');
    }

    public function joinRequests()
    {
        return $this->hasMany(JoinRequest::class);
    }
    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'trip_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function createdTrips()
    {
        return $this->hasMany(Trip::class, 'admin_id');
    }

}