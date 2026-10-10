<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 32)->unique();
            $table->foreignId('ticket_category_id')->constrained('ticket_categories')->restrictOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('g001_m001_unit_id')->nullable()->constrained('g001_m001_units')->restrictOnDelete();
            $table->foreignId('g002_m003_item_management_id')->nullable()
                ->constrained('g002_m003_item_management')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('g004_m008_activity_id')->nullable()
                ->constrained('g004_m008_activities')->restrictOnDelete();
            $table->string('source_key', 191)->nullable()->unique();
            $table->string('title');
            $table->text('description');
            $table->string('priority', 16)->default('normal');
            $table->string('status', 32)->default('open');
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['g001_m001_unit_id', 'status']);
            $table->index(['g002_m003_item_management_id', 'status'], 'tickets_management_status_idx');
        });

        Schema::create('ticket_asset_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            // Only the service may set a whitelisted asset_type/id pair.
            $table->string('asset_type', 20);
            $table->unsignedBigInteger('asset_id');
            $table->timestamps();
            $table->unique(['ticket_id', 'asset_type', 'asset_id'], 'ticket_asset_unique');
            $table->index(['asset_type', 'asset_id']);
        });

        Schema::create('ticket_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignUuid('ticket_comment_id')->nullable()->constrained('ticket_comments')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 24)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('ticket_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('ticket_id')->constrained('tickets')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 48);
            $table->string('from_value')->nullable();
            $table->string('to_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
        });

        foreach ([
            'Kerusakan Barang' => 'damage_item',
            'Kerusakan Ruangan' => 'damage_room',
            'Kendaraan / Servis' => 'vehicle_service',
            'Insiden Peminjaman' => 'loan_incident',
            'Permintaan Fasilitas' => 'facility_request',
        ] as $name => $slug) {
            \Illuminate\Support\Facades\DB::table('ticket_categories')->insert([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_asset_links');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
    }
};
