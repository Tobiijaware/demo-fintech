<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settlement_cycles')) {
            Schema::create('settlement_cycles', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('label');
                $table->timestamp('scheduled_at');
                $table->timestamp('settled_at')->nullable();
                $table->decimal('amount', 18, 2)->default(0);
                $table->unsignedInteger('txn_count')->default(0);
                $table->string('channel', 32)->default('NIBSS');
                $table->string('status', 32);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('scheduled_at');
            });
        }

        if (! Schema::hasTable('partner_banks')) {
            Schema::create('partner_banks', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('account_number', 32);
                $table->string('settlement_window', 64);
                $table->string('sla_status', 32)->default('healthy');
                $table->decimal('failure_rate_24h', 5, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('settlement_exceptions')) {
            Schema::create('settlement_exceptions', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->foreignId('cycle_id')->nullable()->constrained('settlement_cycles')->nullOnDelete();
                $table->string('category', 32);
                $table->string('status', 32);
                $table->string('title');
                $table->text('summary')->nullable();
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('transaction_reference', 64)->nullable();
                $table->json('trace')->nullable();
                $table->text('recommended_action')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('resolution_notes')->nullable();
                $table->foreignId('maker_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('checker_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('category');
                $table->index('status');
                $table->index('cycle_id');
            });
        }

        if (! Schema::hasTable('settlement_exception_events')) {
            Schema::create('settlement_exception_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exception_id')->constrained('settlement_exceptions')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('exception_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_exception_events');
        Schema::dropIfExists('settlement_exceptions');
        Schema::dropIfExists('partner_banks');
        Schema::dropIfExists('settlement_cycles');
    }
};
