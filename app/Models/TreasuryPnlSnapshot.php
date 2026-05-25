<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreasuryPnlSnapshot extends Model
{
    protected $fillable = [
        'period',
        'revenue',
        'costs',
        'net',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'costs' => 'decimal:2',
            'net' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
