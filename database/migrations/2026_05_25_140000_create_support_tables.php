<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('subject');
                $table->text('description')->nullable();
                $table->string('category', 32);
                $table->string('status', 32);
                $table->string('priority', 16);
                $table->string('channel', 32);
                $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('customer_name');
                $table->string('customer_phone', 32)->nullable();
                $table->string('customer_email')->nullable();
                $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
                $table->timestamp('sla_due_at')->nullable();
                $table->boolean('sla_breached')->default(false);
                $table->timestamp('resolved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('maker_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
                $table->index('category');
                $table->index('assignee_id');
                $table->index('sla_breached');
            });
        }

        if (! Schema::hasTable('support_ticket_events')) {
            Schema::create('support_ticket_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('ticket_id');
            });
        }

        if (! Schema::hasTable('reversal_requests')) {
            Schema::create('reversal_requests', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->string('transaction_reference', 64)->nullable();
                $table->decimal('amount', 18, 2);
                $table->text('reason');
                $table->string('status', 32);
                $table->foreignId('maker_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('checker_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('checker_notes')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('maker_id');
            });
        }

        if (! Schema::hasTable('disputes')) {
            Schema::create('disputes', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->foreignId('ticket_id')->nullable()->constrained('support_tickets')->nullOnDelete();
                $table->string('transaction_reference', 64)->nullable();
                $table->decimal('amount', 18, 2);
                $table->text('reason');
                $table->string('status', 32);
                $table->string('customer_name');
                $table->timestamp('opened_at');
                $table->timestamp('due_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
                $table->index('assignee_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('reversal_requests');
        Schema::dropIfExists('support_ticket_events');
        Schema::dropIfExists('support_tickets');
    }
};
