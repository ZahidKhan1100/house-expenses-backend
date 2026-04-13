<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseWallPollVote extends Model
{
    protected $table = 'house_wall_poll_votes';

    protected $fillable = [
        'post_id',
        'option_id',
        'user_id',
    ];

    public function post()
    {
        return $this->belongsTo(HouseWallPost::class, 'post_id');
    }

    public function option()
    {
        return $this->belongsTo(HouseWallPollOption::class, 'option_id');
    }
}

