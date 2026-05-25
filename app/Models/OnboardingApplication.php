<?php

namespace App\Models;

use App\Enums\AccountTier;
use App\Enums\ApplicantType;
use App\Enums\OnboardingChannel;
use App\Enums\OnboardingStatus;
use App\Enums\VerificationCheckStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingApplication extends Model
{
    protected $fillable = [
        'reference',
        'applicant_type',
        'tier',
        'status',
        'channel',
        'verification_status',
        'user_id',
        'business_name',
        'proprietor_name',
        'location',
        'cac_number',
        'business_type',
        'bvn_masked',
        'nin_masked',
        'phone',
        'estimated_settlement',
        'payload',
        'linked_agents',
        'maker_id',
        'checker_id',
        'rejection_reason',
        'query_notes',
        'submitted_at',
        'reviewed_at',
        'sla_due_at',
    ];

    protected function casts(): array
    {
        return [
            'applicant_type' => ApplicantType::class,
            'tier' => AccountTier::class,
            'status' => OnboardingStatus::class,
            'channel' => OnboardingChannel::class,
            'verification_status' => VerificationCheckStatus::class,
            'payload' => 'array',
            'linked_agents' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'sla_due_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OnboardingApplicationEvent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OnboardingDocument::class);
    }

    public function agent(): HasOne
    {
        return $this->hasOne(Agent::class);
    }
}
