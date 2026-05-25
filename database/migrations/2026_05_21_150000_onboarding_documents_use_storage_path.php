<?php

use App\Services\Onboarding\OnboardingDocumentStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('onboarding_documents')) {
            return;
        }

        if (! Schema::hasColumn('onboarding_documents', 'storage_path')) {
            Schema::table('onboarding_documents', function (Blueprint $table) {
                $table->string('storage_path', 500)->nullable()->after('file_size');
            });
        }

        if (Schema::hasColumn('onboarding_documents', 'file_blob')) {
            $storage = app(OnboardingDocumentStorage::class);

            foreach (
                DB::table('onboarding_documents')
                    ->whereNotNull('file_blob')
                    ->whereNull('storage_path')
                    ->cursor() as $doc
            ) {
                $blob = $doc->file_blob;
                if (! is_string($blob) || $blob === '') {
                    continue;
                }

                $path = $storage->store(
                    (int) $doc->onboarding_application_id,
                    (string) $doc->document_type,
                    $blob,
                    (string) $doc->mime_type,
                    (string) $doc->original_filename,
                );

                DB::table('onboarding_documents')
                    ->where('id', $doc->id)
                    ->update(['storage_path' => $path]);
            }

            Schema::table('onboarding_documents', function (Blueprint $table) {
                $table->dropColumn('file_blob');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('onboarding_documents')) {
            return;
        }

        if (! Schema::hasColumn('onboarding_documents', 'file_blob')) {
            Schema::table('onboarding_documents', function (Blueprint $table) {
                $table->binary('file_blob')->nullable();
            });
        }

        if (Schema::hasColumn('onboarding_documents', 'storage_path')) {
            Schema::table('onboarding_documents', function (Blueprint $table) {
                $table->dropColumn('storage_path');
            });
        }
    }
};
