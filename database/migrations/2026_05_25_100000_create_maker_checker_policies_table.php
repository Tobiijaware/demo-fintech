<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maker_checker_policies', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('department', 64);
            $table->string('action', 120);
            $table->text('description');
            $table->string('resource', 64)->nullable();
            $table->string('threshold', 120)->nullable();
            $table->boolean('enforced')->default(true);
            $table->string('enforcement', 16)->default('policy');
            $table->json('role_pairs');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maker_checker_policies');
    }
};
