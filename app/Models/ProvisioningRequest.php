<?php

namespace App\Models;

use App\Enums\ProvisioningRequestStatus;
use App\Enums\ProvisioningRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningRequest extends Model
{
    protected $fillable = [
        'reference',
        'type',
        'status',
        'requested_by_id',
        'checker_id',
        'subject',
        'notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProvisioningRequestType::class,
            'status' => ProvisioningRequestStatus::class,
            'subject' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }
}
