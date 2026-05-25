<?php

namespace App\Models;

use App\Enums\SanctionHitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionsHit extends Model
{
    protected $table = 'sanctions_hits';

    protected $fillable = [
        'reference',
        'list_name',
        'matched_name',
        'match_score',
        'subject_type',
        'subject_id',
        'status',
        'reviewed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SanctionHitStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
