<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TierCriterion extends Model
{
    protected $fillable = [
        'tier_definition_id',
        'key',
        'type',
        'label',
        'description',
        'required',
        'group',
        'rule_group',
        'sort_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'config' => 'array',
        ];
    }

    public function tierDefinition(): BelongsTo
    {
        return $this->belongsTo(TierDefinition::class);
    }
}
