<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->foreignId('onboarding_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('business_name');
            $table->string('proprietor_name');
            $table->string('location')->nullable();
            $table->string('cac_number')->nullable();
            $table->string('tier', 20);
            $table->string('status', 20)->default('active');
            $table->string('region')->nullable();
            $table->string('hub')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('float_balance', 14, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'region']);
            $table->index('hub');
        });

        Schema::create('agent_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('serial_number')->unique();
            $table->string('model')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_terminals');
        Schema::dropIfExists('agents');
    }
};
