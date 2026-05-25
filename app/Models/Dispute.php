<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    protected $fillable = [
        'reference',
        'ticket_id',
        'transaction_reference',
        'amount',
        'reason',
        'status',
        'customer_name',
        'opened_at',
        'due_at',
        'resolution_notes',
        'assignee_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => DisputeStatus::class,
            'opened_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
