<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('applicant_type', 20);
            $table->string('tier', 20);
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('legacy_config')->nullable();
            $table->timestamps();

            $table->unique(['applicant_type', 'tier']);
        });

        Schema::create('tier_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tier_definition_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('type', 32);
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('required')->default(true);
            $table->string('group', 32)->default('kyc');
            $table->string('rule_group', 64)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['tier_definition_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_criteria');
        Schema::dropIfExists('tier_definitions');
    }
};
