<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationsIncident extends Model
{
    protected $fillable = [
        'reference',
        'title',
        'summary',
        'severity',
        'status',
        'owner_name',
        'owner_role',
        'impact',
        'started_at',
        'resolved_at',
        'declared_by_id',
    ];

    protected function casts(): array
    {
        return [
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'impact' => 'array',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OperationsIncidentEvent::class, 'incident_id')->orderBy('created_at');
    }
}
