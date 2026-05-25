<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffSession extends Model
{
    protected $fillable = [
        'user_id',
        'token_hash',
        'ip_address',
        'user_agent',
        'last_active_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
