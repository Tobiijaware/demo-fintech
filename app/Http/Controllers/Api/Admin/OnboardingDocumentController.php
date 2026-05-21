<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OnboardingDocumentType;
use App\Http\Controllers\Api\ApiController;
use App\Models\OnboardingApplication;
use App\Models\OnboardingDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class OnboardingDocumentController extends ApiController
{
    public function index(OnboardingApplication $onboardingApplication): JsonResponse
    {
        $docs = $onboardingApplication->documents()
            ->orderBy('document_type')
            ->get()
            ->map(fn (OnboardingDocument $d) => $this->meta($d));

        return $this->success($docs);
    }

    public function store(OnboardingApplication $onboardingApplication, Request $request): JsonResponse
    {
        $max = config('onboarding.max_document_bytes');

        $validator = Validator::make($request->all(), [
            'document_type' => ['required', Rule::enum(OnboardingDocumentType::class)],
            'file' => ['required', 'file', 'max:'.(int) ($max / 1024)],
        ]);

        $validator->validate();

        $file = $request->file('file');
        $blob = file_get_contents($file->getRealPath());
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (! in_array($mime, $allowed, true)) {
            return $this->error('Only JPEG, PNG, WebP, and PDF files are allowed.', 422);
        }

        if (strlen($blob) > $max) {
            return $this->error('File exceeds maximum upload size.', 422);
        }

        $doc = OnboardingDocument::query()->updateOrCreate(
            [
                'onboarding_application_id' => $onboardingApplication->id,
                'document_type' => $request->input('document_type'),
            ],
            [
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'file_size' => strlen($blob),
                'file_blob' => $blob,
                'uploaded_by' => auth('api')->id(),
            ],
        );

        return $this->success($this->meta($doc), 'Document uploaded.', 201);
    }

    public function show(OnboardingDocument $onboardingDocument): Response
    {
        return response($onboardingDocument->file_blob, 200, [
            'Content-Type' => $onboardingDocument->mime_type,
            'Content-Length' => (string) $onboardingDocument->file_size,
            'Content-Disposition' => 'inline; filename="'.addslashes($onboardingDocument->original_filename).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(OnboardingDocument $onboardingDocument): JsonResponse
    {
        $onboardingDocument->delete();

        return $this->success(null, 'Document removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(OnboardingDocument $doc): array
    {
        return [
            'id' => $doc->id,
            'document_type' => $doc->document_type->value,
            'document_type_label' => $doc->document_type->label(),
            'original_filename' => $doc->original_filename,
            'mime_type' => $doc->mime_type,
            'file_size' => $doc->file_size,
            'is_image' => $doc->isImage(),
            'is_pdf' => $doc->isPdf(),
            'view_url' => url("/api/v1/admin/onboarding/documents/{$doc->id}/file"),
            'created_at' => $doc->created_at?->toIso8601String(),
        ];
    }
}
