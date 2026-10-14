<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('g002_m003_item_management_id')->nullable()
                ->constrained('g002_m003_item_management')->restrictOnDelete();
            $table->string('team_name', 100);
            $table->string('team_type', 24)->default('field');
            $table->string('position', 24)->default('technician');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['team_name', 'team_type', 'is_active'], 'ticket_teams_group_idx');
            $table->index('supervisor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_team_members');
    }
};
