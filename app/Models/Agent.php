<?php

namespace App\Models;

use App\Enums\AccountTier;
use App\Enums\AgentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $fillable = [
        'code',
        'onboarding_application_id',
        'business_name',
        'proprietor_name',
        'location',
        'cac_number',
        'tier',
        'status',
        'region',
        'hub',
        'user_id',
        'float_balance',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tier' => AccountTier::class,
            'status' => AgentStatus::class,
            'float_balance' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function onboardingApplication(): BelongsTo
    {
        return $this->belongsTo(OnboardingApplication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(AgentTerminal::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AgentCommission::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
