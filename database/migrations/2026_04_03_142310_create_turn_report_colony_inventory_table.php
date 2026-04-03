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
        Schema::create('turn_report_colony_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_report_colony_id')->constrained()->cascadeOnDelete();
            $table->string('unit_code');
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
        Schema::dropIfExists('turn_report_colony_inventory');
    }
};
