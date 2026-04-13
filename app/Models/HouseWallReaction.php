<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseWallReaction extends Model
{
    protected $table = 'house_wall_reactions';

    protected $fillable = [
        'post_id',
        'user_id',
    ];

    public function post()
    {
        return $this->belongsTo(HouseWallPost::class, 'post_id');
    }
}

