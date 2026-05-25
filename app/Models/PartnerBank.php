<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerBank extends Model
{
    protected $fillable = [
        'name',
        'account_number',
        'settlement_window',
        'sla_status',
        'failure_rate_24h',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'failure_rate_24h' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
