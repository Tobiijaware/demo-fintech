<?php

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    protected $fillable = [
        'policy_id',
        'resource_type',
        'resource_id',
        'maker_id',
        'status',
        'summary',
        'payload',
        'checker_id',
        'checker_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalRequestStatus::class,
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(MakerCheckerPolicy::class, 'policy_id');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maker_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }
}
