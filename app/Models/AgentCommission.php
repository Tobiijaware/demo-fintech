<?php

namespace App\Models;

use App\Enums\AgentCommissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommission extends Model
{
    protected $fillable = [
        'agent_id',
        'period',
        'gross_volume',
        'commission_amount',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_volume' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'status' => AgentCommissionStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
