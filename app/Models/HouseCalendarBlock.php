<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseCalendarBlock extends Model
{
    protected $fillable = [
        'house_id',
        'user_id',
        'starts_on',
        'ends_on',
        'kind',
        'reason_emoji',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
