<?php

namespace App\Models;

use App\Enums\SettlementExceptionCategory;
use App\Enums\SettlementExceptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementException extends Model
{
    protected $fillable = [
        'reference',
        'cycle_id',
        'category',
        'status',
        'title',
        'summary',
        'amount',
        'transaction_reference',
        'trace',
        'recommended_action',
        'resolved_at',
        'resolved_by_id',
        'resolution_notes',
        'maker_id',
        'checker_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'category' => SettlementExceptionCategory::class,
            'status' => SettlementExceptionStatus::class,
            'trace' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(SettlementCycle::class, 'cycle_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SettlementExceptionEvent::class, 'exception_id')->orderBy('created_at');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }
}
