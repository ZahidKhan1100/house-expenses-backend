<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseWallPollOption extends Model
{
    protected $table = 'house_wall_poll_options';

    protected $fillable = [
        'post_id',
        'text',
        'sort_order',
    ];

    public function post()
    {
        return $this->belongsTo(HouseWallPost::class, 'post_id');
    }
}

