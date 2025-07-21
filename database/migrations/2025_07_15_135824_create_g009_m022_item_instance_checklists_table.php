<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('g009_m022_item_instance_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('g002_m015_item_instance_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->date('date')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo')->nullable();
            $table->dateTime('checklist_date')->nullable();
            $table->timestamps();

             // Define foreign key with a shorter custom name
            $table->foreign('g002_m015_item_instance_id', 'fk_item_checklist_to_instances')
                ->references('id')
                ->on('g002_m015_item_instances')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('g009_m022_item_instance_checklists');
    }
};
