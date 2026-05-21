<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('session_id', 32)->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('direction', 10);
            $table->decimal('amount', 18, 2);
            $table->decimal('fee', 18, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 20)->default('success');
            $table->string('counterparty_name')->nullable();
            $table->string('counterparty_account', 10)->nullable();
            $table->string('counterparty_bank')->nullable();
            $table->text('narrative')->nullable();
            $table->foreignId('linked_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
