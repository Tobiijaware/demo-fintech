<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('float_positions', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 128);
            $table->string('account_number', 32);
            $table->string('account_label', 128)->nullable();
            $table->decimal('balance', 14, 2)->default(0);
            $table->unsignedTinyInteger('utilization_pct')->default(0);
            $table->string('status', 20)->default('healthy');
            $table->string('currency', 3)->default('NGN');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('fee_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('product_key', 64);
            $table->string('product_label', 128);
            $table->string('fee_type', 32);
            $table->decimal('rate_or_amount', 14, 4);
            $table->date('effective_from');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['product_key', 'active']);
        });

        Schema::create('treasury_pnl_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->decimal('costs', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_pnl_snapshots');
        Schema::dropIfExists('fee_schedules');
        Schema::dropIfExists('float_positions');
    }
};
