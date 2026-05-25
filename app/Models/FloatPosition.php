<?php

namespace App\Models;

use App\Enums\FloatPositionStatus;
use Illuminate\Database\Eloquent\Model;

class FloatPosition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bank_name',
        'account_number',
        'account_label',
        'balance',
        'utilization_pct',
        'status',
        'currency',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'utilization_pct' => 'integer',
            'status' => FloatPositionStatus::class,
            'updated_at' => 'datetime',
        ];
    }
}
