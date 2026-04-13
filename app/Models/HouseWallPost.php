<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseWallPost extends Model
{
    protected $table = 'house_wall_posts';

    protected $fillable = [
        'house_id',
        'user_id',
        'type',
        'caption',
        'image_url',
        'poll_question',
        'system_payload',
    ];

    protected $casts = [
        'system_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pollOptions()
    {
        return $this->hasMany(HouseWallPollOption::class, 'post_id');
    }

    public function votes()
    {
        return $this->hasMany(HouseWallPollVote::class, 'post_id');
    }

    public function reactions()
    {
        return $this->hasMany(HouseWallReaction::class, 'post_id');
    }
}

