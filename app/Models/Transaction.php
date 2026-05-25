<?php

namespace App\Models;

use App\Enums\TransactionDirection;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'reference',
        'session_id',
        'user_id',
        'wallet_id',
        'agent_id',
        'type',
        'direction',
        'amount',
        'fee',
        'currency',
        'status',
        'counterparty_name',
        'counterparty_account',
        'counterparty_bank',
        'narrative',
        'linked_transaction_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'status' => TransactionStatus::class,
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function linkedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_transaction_id');
    }
}
