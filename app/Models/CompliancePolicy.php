<?php

namespace App\Models;

use App\Enums\PolicyCategory;
use App\Enums\PolicyStatus;
use Illuminate\Database\Eloquent\Model;

class CompliancePolicy extends Model
{
    protected $fillable = [
        'reference',
        'name',
        'version',
        'category',
        'owner',
        'effective_date',
        'review_due',
        'status',
        'summary',
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'category' => PolicyCategory::class,
            'status' => PolicyStatus::class,
            'effective_date' => 'date',
            'review_due' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
