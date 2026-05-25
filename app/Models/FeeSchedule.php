<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeSchedule extends Model
{
    protected $fillable = [
        'product_key',
        'product_label',
        'fee_type',
        'rate_or_amount',
        'effective_from',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'rate_or_amount' => 'decimal:4',
            'effective_from' => 'date',
            'active' => 'boolean',
        ];
    }
}
