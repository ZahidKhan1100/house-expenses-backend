<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KarmaLedger extends Model
{
    public $timestamps = false;

    protected $table = 'karma_ledger';

    protected $fillable = [
        'user_id',
        'house_id',
        'points',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
