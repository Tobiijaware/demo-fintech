<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tier_definitions', function (Blueprint $table) {
            $table->json('limits')->nullable()->after('legacy_config');
        });
    }

    public function down(): void
    {
        Schema::table('tier_definitions', function (Blueprint $table) {
            $table->dropColumn('limits');
        });
    }
};
