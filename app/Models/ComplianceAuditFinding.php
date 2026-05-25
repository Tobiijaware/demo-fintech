<?php

namespace App\Models;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use Illuminate\Database\Eloquent\Model;

class ComplianceAuditFinding extends Model
{
    protected $fillable = [
        'reference',
        'area',
        'title',
        'severity',
        'status',
        'owner',
        'due_date',
        'opened_at',
        'remediation_notes',
    ];

    protected function casts(): array
    {
        return [
            'severity' => FindingSeverity::class,
            'status' => FindingStatus::class,
            'due_date' => 'date',
            'opened_at' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
