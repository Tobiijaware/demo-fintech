<?php

namespace App\Models;

use App\Enums\AmlCaseStatus;
use App\Enums\AmlSubjectType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AmlCase extends Model
{
    protected $fillable = [
        'reference',
        'alert_id',
        'title',
        'summary',
        'status',
        'assignee_id',
        'subject_type',
        'subject_id',
        'opened_at',
        'closed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => AmlCaseStatus::class,
            'subject_type' => AmlSubjectType::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(AmlAlert::class, 'alert_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AmlCaseEvent::class, 'case_id')->orderBy('created_at');
    }

    public function strFilings(): HasMany
    {
        return $this->hasMany(StrFiling::class, 'case_id');
    }

    public function walletFreezes(): HasMany
    {
        return $this->hasMany(WalletFreeze::class, 'case_id');
    }
}
