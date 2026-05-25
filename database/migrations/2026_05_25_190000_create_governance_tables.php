<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->json('value');
                $table->string('group', 32);
                $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('group');
            });
        }

        if (! Schema::hasTable('provisioning_requests')) {
            Schema::create('provisioning_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('type', 32);
                $table->string('status', 32)->default('pending');
                $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('checker_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('subject');
                $table->text('notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'type']);
                $table->index('requested_by_id');
            });
        }

        if (! Schema::hasTable('staff_sessions')) {
            Schema::create('staff_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 512)->nullable();
                $table->timestamp('last_active_at');
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'revoked_at']);
                $table->index('last_active_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_sessions');
        Schema::dropIfExists('provisioning_requests');
        Schema::dropIfExists('system_settings');
    }
};
