<?php

namespace App\Models;

use App\Enums\SettlementCycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementCycle extends Model
{
    protected $fillable = [
        'reference',
        'label',
        'scheduled_at',
        'settled_at',
        'amount',
        'txn_count',
        'channel',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'settled_at' => 'datetime',
            'amount' => 'decimal:2',
            'txn_count' => 'integer',
            'status' => SettlementCycleStatus::class,
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(SettlementException::class, 'cycle_id');
    }
}
