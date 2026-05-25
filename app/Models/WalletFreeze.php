<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletFreeze extends Model
{
    protected $fillable = [
        'wallet_id',
        'user_id',
        'case_id',
        'reason',
        'frozen_by_id',
        'active',
        'unfrozen_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'unfrozen_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amlCase(): BelongsTo
    {
        return $this->belongsTo(AmlCase::class, 'case_id');
    }

    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by_id');
    }
}
