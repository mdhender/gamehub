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
        Schema::create('colony_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_id')->constrained()->cascadeOnDelete();
            $table->integer('unit');
            $table->integer('tech_level');
            $table->integer('quantity_assembled');
            $table->integer('quantity_disassembled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colony_template_items');
    }
};
