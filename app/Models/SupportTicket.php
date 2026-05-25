<?php

namespace App\Models;

use App\Enums\SupportChannel;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'reference',
        'subject',
        'description',
        'category',
        'status',
        'priority',
        'channel',
        'assignee_id',
        'customer_user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'wallet_id',
        'sla_due_at',
        'sla_breached',
        'resolved_at',
        'metadata',
        'maker_id',
    ];

    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'channel' => SupportChannel::class,
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_breached' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class, 'ticket_id')->orderBy('created_at');
    }

    public function reversalRequests(): HasMany
    {
        return $this->hasMany(ReversalRequest::class, 'ticket_id');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'ticket_id');
    }
}
