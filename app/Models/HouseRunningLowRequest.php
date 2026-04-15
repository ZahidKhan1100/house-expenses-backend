<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HouseRunningLowRequest extends Model
{
    protected $table = 'house_running_low_requests';

    protected $fillable = [
        'house_id',
        'item_key',
        'display_label',
        'status',
        'created_by',
        'fulfilled_by',
        'fulfilled_post_id',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
