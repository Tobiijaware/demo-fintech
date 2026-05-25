<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aml_alerts')) {
            Schema::create('aml_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('severity', 16);
                $table->string('title');
                $table->text('narrative')->nullable();
                $table->string('typology', 64)->nullable();
                $table->unsignedTinyInteger('score')->default(0);
                $table->string('subject_type', 32);
                $table->string('subject_id', 64);
                $table->string('status', 32);
                $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('severity');
                $table->index(['subject_type', 'subject_id']);
            });
        }

        if (! Schema::hasTable('aml_cases')) {
            Schema::create('aml_cases', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->foreignId('alert_id')->nullable()->constrained('aml_alerts')->nullOnDelete();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->string('status', 32);
                $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('subject_type', 32);
                $table->string('subject_id', 64);
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('alert_id');
            });
        }

        if (! Schema::hasTable('aml_case_events')) {
            Schema::create('aml_case_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('case_id')->constrained('aml_cases')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('case_id');
            });
        }

        if (! Schema::hasTable('sanctions_hits')) {
            Schema::create('sanctions_hits', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('list_name');
                $table->string('matched_name');
                $table->unsignedTinyInteger('match_score')->default(0);
                $table->string('subject_type', 32);
                $table->string('subject_id', 64);
                $table->string('status', 32);
                $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
            });
        }

        if (! Schema::hasTable('str_filings')) {
            Schema::create('str_filings', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->foreignId('case_id')->nullable()->constrained('aml_cases')->nullOnDelete();
                $table->string('title');
                $table->text('narrative')->nullable();
                $table->decimal('amount_ngn', 18, 2)->default(0);
                $table->string('status', 32);
                $table->foreignId('maker_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('checker_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->string('nfiu_reference', 64)->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('maker_id');
            });
        }

        if (! Schema::hasTable('wallet_freezes')) {
            Schema::create('wallet_freezes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('case_id')->nullable()->constrained('aml_cases')->nullOnDelete();
                $table->text('reason');
                $table->foreignId('frozen_by_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('active')->default(true);
                $table->timestamp('unfrozen_at')->nullable();
                $table->timestamps();

                $table->index('active');
                $table->index('wallet_id');
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_freezes');
        Schema::dropIfExists('str_filings');
        Schema::dropIfExists('sanctions_hits');
        Schema::dropIfExists('aml_case_events');
        Schema::dropIfExists('aml_cases');
        Schema::dropIfExists('aml_alerts');
    }
};
