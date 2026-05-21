<?php

namespace App\Models;

use App\Enums\OnboardingDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingDocument extends Model
{
    protected $fillable = [
        'onboarding_application_id',
        'document_type',
        'original_filename',
        'mime_type',
        'file_size',
        'file_blob',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => OnboardingDocumentType::class,
            'file_blob' => 'string',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(OnboardingApplication::class, 'onboarding_application_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }
}
