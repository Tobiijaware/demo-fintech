<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationsIncidentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'incident_id',
        'actor_name',
        'action',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(OperationsIncident::class, 'incident_id');
    }
}
