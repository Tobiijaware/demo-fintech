<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('firstname')->nullable()->after('id');
            $table->string('lastname')->nullable()->after('firstname');
            $table->string('gender', 20)->nullable()->after('lastname');
            $table->date('dob')->nullable()->after('gender');
            $table->string('phone', 20)->nullable()->unique()->after('dob');
            $table->string('role', 20)->default('customer')->after('email');
            $table->string('status', 20)->default('pending')->after('role');
            $table->string('bvn', 11)->nullable()->unique()->after('status');
            $table->string('nin', 11)->nullable()->unique()->after('bvn');
            $table->string('pin')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'firstname',
                'lastname',
                'gender',
                'dob',
                'phone',
                'role',
                'status',
                'bvn',
                'nin',
                'pin',
            ]);
            $table->string('name')->after('id');
        });
    }
};
