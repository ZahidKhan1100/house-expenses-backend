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

    /**
     * House members for shared living features (karma, leaderboard, calendar, wall, etc.).
     * QR / instant joins use status {@see JoinHouseByQRCode} `active` — not `approved`.
     *
     * @var list<string>
     */
    public const HOUSE_MEMBER_STATUSES = ['admin', 'approved', 'active'];

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
        'is_founder',
        'karma_balance',
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

    public function pushTokens()
    {
        return $this->hasMany(UserPushToken::class);
    }

    /**
     * All Expo push tokens for this user (table + legacy column), deduped.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function allExpoPushTokens(): \Illuminate\Support\Collection
    {
        $tokens = $this->relationLoaded('pushTokens')
            ? $this->pushTokens->pluck('token')
            : $this->pushTokens()->pluck('token');

        if (! empty($this->expo_push_token)) {
            $tokens = $tokens->push($this->expo_push_token);
        }

        return $tokens->filter()->unique()->values();
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->trashed();
    }

}
