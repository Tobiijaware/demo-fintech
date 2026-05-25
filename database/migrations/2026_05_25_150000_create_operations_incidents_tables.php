<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operations_incidents')) {
            Schema::create('operations_incidents', function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32)->unique();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->string('severity', 8);
                $table->string('status', 16);
                $table->string('owner_name');
                $table->string('owner_role')->nullable();
                $table->json('impact')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('resolved_at')->nullable();
                $table->foreignId('declared_by_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
                $table->index('severity');
                $table->index('started_at');
            });
        }

        if (! Schema::hasTable('operations_incident_events')) {
            Schema::create('operations_incident_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('incident_id')->constrained('operations_incidents')->cascadeOnDelete();
                $table->string('actor_name');
                $table->string('action');
                $table->timestamp('created_at')->useCurrent();

                $table->index('incident_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_incident_events');
        Schema::dropIfExists('operations_incidents');
    }
};
