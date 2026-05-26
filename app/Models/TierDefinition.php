<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TierDefinition extends Model
{
    protected $fillable = [
        'applicant_type',
        'tier',
        'label',
        'description',
        'active',
        'sort_order',
        'legacy_config',
        'limits',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'legacy_config' => 'array',
            'limits' => 'array',
        ];
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(TierCriterion::class)->orderBy('sort_order');
    }
}
