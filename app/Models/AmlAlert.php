<?php

namespace App\Models;

use App\Enums\AmlAlertSeverity;
use App\Enums\AmlAlertStatus;
use App\Enums\AmlSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AmlAlert extends Model
{
    protected $fillable = [
        'reference',
        'severity',
        'title',
        'narrative',
        'typology',
        'score',
        'subject_type',
        'subject_id',
        'status',
        'assignee_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'severity' => AmlAlertSeverity::class,
            'subject_type' => AmlSubjectType::class,
            'status' => AmlAlertStatus::class,
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(AmlCase::class, 'alert_id');
    }
}
