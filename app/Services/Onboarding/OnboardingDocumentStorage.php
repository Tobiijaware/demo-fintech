<?php

namespace App\Services\Onboarding;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OnboardingDocumentStorage
{
    private const DISK = 'local';

    private const ROOT = 'onboarding-documents';

    public function store(int $applicationId, string $documentType, string $contents, string $mime, string $originalFilename): string
    {
        $extension = $this->resolveExtension($mime, $originalFilename);
        $path = sprintf(
            '%s/%d/%s.%s',
            self::ROOT,
            $applicationId,
            Str::slug($documentType, '_'),
            $extension,
        );

        Storage::disk(self::DISK)->put($path, $contents);

        return $path;
    }

    public function get(string $path): ?string
    {
        if (! Storage::disk(self::DISK)->exists($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->get($path);
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    protected function resolveExtension(string $mime, string $originalFilename): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'bin'),
        };
    }
}
