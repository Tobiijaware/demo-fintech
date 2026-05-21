<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backoffice_roles')) {
            Schema::create('backoffice_roles', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('name');
                $table->string('department', 64)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->string('name');
                $table->string('group', 64)->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('backoffice_role_permission')) {
            Schema::create('backoffice_role_permission', function (Blueprint $table) {
                $table->id();
                $table->foreignId('backoffice_role_id')->constrained('backoffice_roles')->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
                $table->string('level', 16);
                $table->unique(['backoffice_role_id', 'permission_id'], 'bo_role_perm_unique');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'user_type')) {
                $table->string('user_type', 16)->default('customer')->after('email');
            }
            if (! Schema::hasColumn('users', 'backoffice_role_id')) {
                $table->foreignId('backoffice_role_id')->nullable()->after('user_type')->constrained('backoffice_roles')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title', 120)->nullable()->after('backoffice_role_id');
            }
            if (! Schema::hasColumn('users', 'hub')) {
                $table->string('hub', 64)->nullable()->after('job_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'backoffice_role_id')) {
                $table->dropConstrainedForeignId('backoffice_role_id');
            }
            $cols = array_filter(
                ['user_type', 'job_title', 'hub'],
                fn ($c) => Schema::hasColumn('users', $c),
            );
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        Schema::dropIfExists('backoffice_role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('backoffice_roles');
    }
};
