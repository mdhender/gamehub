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
        Schema::create('home_system_template_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_system_template_planet_id')->constrained()->cascadeOnDelete();
            $table->string('resource');
            $table->integer('yield_pct');
            $table->integer('quantity_remaining');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_system_template_deposits');
    }
};
