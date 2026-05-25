<?php

namespace App\Models;

use App\Enums\FilingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegulatoryFiling extends Model
{
    protected $fillable = [
        'reference',
        'title',
        'regulator',
        'due_date',
        'status',
        'owner_name',
        'owner_id',
        'frequency',
        'description',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FilingStatus::class,
            'due_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
