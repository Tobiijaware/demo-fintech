<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('applicant_type', 20);
            $table->string('tier', 20);
            $table->string('status', 30)->default('pending_review');
            $table->string('channel', 20);
            $table->string('verification_status', 30)->default('pending');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_name')->nullable();
            $table->string('proprietor_name')->nullable();
            $table->string('location')->nullable();
            $table->string('cac_number')->nullable();
            $table->string('business_type')->nullable();
            $table->string('bvn_masked', 32)->nullable();
            $table->string('nin_masked', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('estimated_settlement')->nullable();
            $table->json('payload')->nullable();
            $table->json('linked_agents')->nullable();
            $table->foreignId('maker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->text('query_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'applicant_type']);
            $table->index(['tier', 'status']);
        });

        Schema::create('onboarding_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_application_events');
        Schema::dropIfExists('onboarding_applications');
    }
};
