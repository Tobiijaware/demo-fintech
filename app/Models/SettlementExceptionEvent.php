<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementExceptionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'exception_id',
        'actor_id',
        'action',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function exception(): BelongsTo
    {
        return $this->belongsTo(SettlementException::class, 'exception_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
