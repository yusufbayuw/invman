<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('g002_m007_items', function (Blueprint $table): void {
            // Legacy inventory always remains individually tracked.
            $table->string('tracking_mode', 20)->default('individual');
        });

        Schema::create('asset_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_instance_id')->constrained('g002_m015_item_instances')->restrictOnDelete();
            $table->foreignId('child_instance_id')->constrained('g002_m015_item_instances')->restrictOnDelete();
            $table->string('relation_type', 30); // component, contained, installed, bundle
            $table->timestamps();
            $table->unique(['parent_instance_id', 'child_instance_id', 'relation_type'], 'asset_relation_pair_unique');
            $table->index(['child_instance_id', 'relation_type'], 'asset_relation_child_type');
        });

        Schema::create('asset_bulk_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('g002_m007_items')->restrictOnDelete();
            $table->integer('delta');
            $table->string('reason', 120);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_bulk_movements');
        Schema::dropIfExists('asset_relations');
        Schema::table('g002_m007_items', fn (Blueprint $table) => $table->dropColumn('tracking_mode'));
    }
};
