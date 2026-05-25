<?php

namespace App\Models;

use App\Enums\AgentTerminalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTerminal extends Model
{
    protected $fillable = [
        'agent_id',
        'serial_number',
        'model',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentTerminalStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
