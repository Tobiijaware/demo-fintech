<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_freezes', function (Blueprint $table) {
            $table->string('restriction_type', 32)->default('full_freeze')->after('reason');
            $table->text('customer_message')->nullable()->after('restriction_type');
            $table->string('source', 32)->default('aml')->after('customer_message');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_freezes', function (Blueprint $table) {
            $table->dropColumn(['restriction_type', 'customer_message', 'source']);
        });
    }
};
