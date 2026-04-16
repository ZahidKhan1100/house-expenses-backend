<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Record extends Model
{
    protected $fillable = [
        'expense_id',
        'added_by',
        'amount',
        'category_id',
        'description',
        'included_mates',
        'paid_by',
        'timestamp',
        'added_by_name',
        'paid_by_name',
        'split_method',
        'bill_period_days',
    ];

    protected $casts = [
        'included_mates' => 'array',
        'timestamp' => 'datetime',
        'bill_period_days' => 'integer',
    ];

    protected $appends = [
        'excluded_days_by_user',
    ];

    public function getExcludedDaysByUserAttribute(): array
    {
        try {
            $rows = DB::table('record_user')
                ->where('record_id', (int) $this->id)
                ->get(['user_id', 'excluded_days']);

            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r->user_id] = (int) ($r->excluded_days ?? 0);
            }
            return $out;
        } catch (\Throwable) {
            // Safe fallback if table isn't migrated yet.
            return [];
        }
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}