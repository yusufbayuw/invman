<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('operational_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('entity_type', 32);
            $table->string('entity_id', 64);
            $table->string('action', 64);
            $table->string('from_status', 48)->nullable();
            $table->string('to_status', 48)->nullable();
            $table->text('reason');
            $table->json('details')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id', 'created_at'], 'overrides_entity_audit_idx');
            $table->index(['actor_id', 'created_at'], 'overrides_actor_audit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_overrides');
    }
};
