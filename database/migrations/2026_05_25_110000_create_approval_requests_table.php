<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('policy_id', 64);
            $table->string('resource_type', 64);
            $table->string('resource_id', 64);
            $table->foreignId('maker_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('summary', 500);
            $table->json('payload');
            $table->foreignId('checker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('checker_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('policy_id')->references('id')->on('maker_checker_policies')->cascadeOnDelete();
            $table->index(['status', 'resource_type']);
            $table->index(['maker_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
