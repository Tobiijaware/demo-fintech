<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingApplicationEvent extends Model
{
    protected $fillable = [
        'onboarding_application_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'notes',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(OnboardingApplication::class, 'onboarding_application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
