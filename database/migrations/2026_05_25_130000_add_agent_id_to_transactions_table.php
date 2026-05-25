<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('wallet_id')->constrained()->nullOnDelete();
            $table->index(['agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropIndex(['agent_id', 'created_at']);
            $table->dropColumn('agent_id');
        });
    }
};
