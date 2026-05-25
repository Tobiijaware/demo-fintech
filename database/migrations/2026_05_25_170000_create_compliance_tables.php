<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regulators')) {
            Schema::create('regulators', function (Blueprint $table) {
                $table->id();
                $table->string('code', 16)->unique();
                $table->string('name');
                $table->string('status', 64);
                $table->date('last_submission')->nullable();
                $table->date('next_due')->nullable();
                $table->string('contact_email')->nullable();
                $table->unsignedInteger('filings_ytd')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('regulatory_filings')) {
            Schema::create('regulatory_filings', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('title');
                $table->string('regulator', 16);
                $table->date('due_date');
                $table->string('status', 32);
                $table->string('owner_name');
                $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('frequency', 32);
                $table->text('description')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->index('regulator');
                $table->index('status');
                $table->index('due_date');
            });
        }

        if (! Schema::hasTable('compliance_audit_findings')) {
            Schema::create('compliance_audit_findings', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('area', 64);
                $table->string('title');
                $table->string('severity', 16);
                $table->string('status', 32);
                $table->string('owner');
                $table->date('due_date');
                $table->date('opened_at');
                $table->text('remediation_notes')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('severity');
            });
        }

        if (! Schema::hasTable('compliance_policies')) {
            Schema::create('compliance_policies', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('name');
                $table->string('version', 16);
                $table->string('category', 32);
                $table->string('owner');
                $table->date('effective_date');
                $table->date('review_due');
                $table->string('status', 32);
                $table->text('summary')->nullable();
                $table->string('document_path')->nullable();
                $table->timestamps();

                $table->index('category');
                $table->index('status');
                $table->index('review_due');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_policies');
        Schema::dropIfExists('compliance_audit_findings');
        Schema::dropIfExists('regulatory_filings');
        Schema::dropIfExists('regulators');
    }
};
