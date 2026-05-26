<?php

namespace Database\Seeders;

use App\Enums\OnboardingDocumentType;
use App\Models\OnboardingApplication;
use App\Models\OnboardingDocument;
use App\Services\Onboarding\OnboardingDocumentStorage;
use Illuminate\Database\Seeder;

class OnboardingDocumentsSeeder extends Seeder
{
    /**
     * @var array<string, list<array{document_type: OnboardingDocumentType, mime_type: string, filename: string}>>
     */
    private array $demoDocumentsByReference = [
        'KYC-2026-08291' => [
            ['document_type' => OnboardingDocumentType::UtilityBill, 'mime_type' => 'image/jpeg', 'filename' => 'ikeja-utility-bill.jpg'],
            ['document_type' => OnboardingDocumentType::DirectorsId, 'mime_type' => 'image/jpeg', 'filename' => 'chinwe-national-id.jpg'],
        ],
        'KYC-2026-08288' => [
            ['document_type' => OnboardingDocumentType::CacCertificate, 'mime_type' => 'application/pdf', 'filename' => 'cac-certificate.pdf'],
            ['document_type' => OnboardingDocumentType::UtilityBill, 'mime_type' => 'image/jpeg', 'filename' => 'onitsha-utility-bill.jpg'],
            ['document_type' => OnboardingDocumentType::DirectorsId, 'mime_type' => 'image/jpeg', 'filename' => 'ngozi-national-id.jpg'],
        ],
        'KYC-2026-08044' => [
            ['document_type' => OnboardingDocumentType::UtilityBill, 'mime_type' => 'image/jpeg', 'filename' => 'abuja-utility-bill.jpg'],
            ['document_type' => OnboardingDocumentType::DirectorsId, 'mime_type' => 'image/jpeg', 'filename' => 'tunde-national-id.jpg'],
        ],
        'KYC-2026-07880' => [
            ['document_type' => OnboardingDocumentType::UtilityBill, 'mime_type' => 'image/jpeg', 'filename' => 'warri-utility-bill.jpg'],
            ['document_type' => OnboardingDocumentType::DirectorsId, 'mime_type' => 'image/jpeg', 'filename' => 'paul-national-id.jpg'],
        ],
    ];

    public function run(): void
    {
        $storage = app(OnboardingDocumentStorage::class);

        foreach ($this->demoDocumentsByReference as $reference => $documents) {
            $application = OnboardingApplication::query()->where('reference', $reference)->first();
            if (! $application) {
                continue;
            }

            foreach ($documents as $spec) {
                $doc = OnboardingDocument::query()->firstOrCreate(
                    [
                        'onboarding_application_id' => $application->id,
                        'document_type' => $spec['document_type'],
                    ],
                    [
                        'original_filename' => $spec['filename'],
                        'mime_type' => $spec['mime_type'],
                        'file_size' => 0,
                        'storage_path' => '',
                    ],
                );

                if ($doc->storage_path && $storage->get($doc->storage_path) !== null) {
                    continue;
                }

                $placeholder = OnboardingDocument::query()
                    ->with('application')
                    ->find($doc->id) ?? $doc->load('application');

                $contents = $storage->buildPlaceholder($placeholder);
                $path = $storage->store(
                    $application->id,
                    $spec['document_type']->value,
                    $contents,
                    $spec['mime_type'],
                    $spec['filename'],
                );

                $doc->update([
                    'original_filename' => $spec['filename'],
                    'mime_type' => $spec['mime_type'],
                    'file_size' => strlen($contents),
                    'storage_path' => $path,
                ]);
            }
        }

        OnboardingDocument::query()
            ->with('application')
            ->get()
            ->each(function (OnboardingDocument $document) use ($storage): void {
                if ($document->storage_path && $storage->get($document->storage_path) !== null) {
                    return;
                }

                if (! $document->storage_path) {
                    $contents = $storage->buildPlaceholder($document);
                    $path = $storage->store(
                        $document->onboarding_application_id,
                        $document->document_type->value,
                        $contents,
                        $document->mime_type,
                        $document->original_filename,
                    );

                    $document->update([
                        'storage_path' => $path,
                        'file_size' => strlen($contents),
                    ]);

                    return;
                }

                $storage->resolveContents($document);
            });
    }
}
