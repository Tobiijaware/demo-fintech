<?php

namespace App\Services\Onboarding;

use App\Models\OnboardingDocument;
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

    public function resolveContents(OnboardingDocument $document): ?string
    {
        $existing = $this->get($document->storage_path);
        if ($existing !== null) {
            return $existing;
        }

        if (! $document->storage_path) {
            return null;
        }

        $contents = $this->buildPlaceholder($document);
        Storage::disk(self::DISK)->put($document->storage_path, $contents);

        if ((int) $document->file_size !== strlen($contents)) {
            $document->update(['file_size' => strlen($contents)]);
        }

        return $contents;
    }

    public function buildPlaceholder(OnboardingDocument $document): string
    {
        $label = $document->document_type->label();
        $reference = $document->application?->reference ?? "Application #{$document->onboarding_application_id}";
        $title = "{$label} — {$reference}";

        if ($document->isPdf()) {
            return $this->buildPlaceholderPdf($title, $document->original_filename);
        }

        if ($document->isImage()) {
            return $this->buildPlaceholderImage(
                $title,
                $document->mime_type === 'image/png' ? 'png' : 'jpg',
            );
        }

        return "{$title}\n\nDemo placeholder document.";
    }

    protected function buildPlaceholderImage(string $title, string $format): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                true,
            ) ?: "{$title}\n";
        }

        $width = 960;
        $height = 640;
        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 244, 245, 247);
        $ink = imagecolorallocate($image, 15, 23, 40);
        $muted = imagecolorallocate($image, 107, 114, 128);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagefilledrectangle($image, 40, 40, $width - 40, $height - 40, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 72, 96, 'iWallet Demo Document', $ink);
        imagestring($image, 3, 72, 140, $this->truncateImageLabel($title), $muted);
        imagestring($image, 2, 72, 180, 'Generated for review in demo environments.', $muted);

        ob_start();
        if ($format === 'png') {
            imagepng($image);
        } else {
            imagejpeg($image, null, 88);
        }
        $contents = ob_get_clean() ?: '';
        imagedestroy($image);

        return $contents;
    }

    protected function buildPlaceholderPdf(string $title, string $filename): string
    {
        $line1 = $this->escapePdfText($this->truncateImageLabel($title));
        $line2 = $this->escapePdfText($this->truncateImageLabel($filename));

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];

        $stream = "BT /F1 16 Tf 72 720 Td (iWallet Demo Document) Tj 0 -28 Td /F1 12 Tf ({$line1}) Tj 0 -20 Td ({$line2}) Tj ET";
        $objects[] = '4 0 obj << /Length '.strlen($stream)." >> stream\n{$stream}\nendstream endobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 ".count($offsets)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size ".count($offsets)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    protected function truncateImageLabel(string $title): string
    {
        return strlen($title) > 72 ? substr($title, 0, 69).'...' : $title;
    }

    protected function escapePdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
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
