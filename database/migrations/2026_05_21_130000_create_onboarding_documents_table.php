<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_documents')) {
            return;
        }

        Schema::create('onboarding_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_application_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedInteger('file_size');
            $table->string('storage_path', 500);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['onboarding_application_id', 'document_type'], 'onb_docs_app_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_documents');
    }
};
