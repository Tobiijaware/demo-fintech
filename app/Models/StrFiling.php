<?php

namespace App\Models;

use App\Enums\StrFilingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrFiling extends Model
{
    protected $fillable = [
        'reference',
        'case_id',
        'title',
        'narrative',
        'amount_ngn',
        'status',
        'maker_id',
        'checker_id',
        'submitted_at',
        'nfiu_reference',
    ];

    protected function casts(): array
    {
        return [
            'amount_ngn' => 'decimal:2',
            'status' => StrFilingStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function amlCase(): BelongsTo
    {
        return $this->belongsTo(AmlCase::class, 'case_id');
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
