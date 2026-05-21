<?php

namespace App\Models;

use App\Enums\WalletStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'account_number',
        'balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'status' => WalletStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
