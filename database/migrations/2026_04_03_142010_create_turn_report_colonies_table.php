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
        Schema::create('turn_report_colonies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_report_id')->constrained()->cascadeOnDelete();
            $table->integer('source_colony_id')->nullable();
            $table->string('name');
            $table->string('kind');
            $table->integer('tech_level');
            $table->integer('planet_id')->nullable();
            $table->integer('orbit');
            $table->integer('star_x');
            $table->integer('star_y');
            $table->integer('star_z');
            $table->integer('star_sequence');
            $table->boolean('is_on_surface');
            $table->float('rations');
            $table->float('sol');
            $table->float('birth_rate');
            $table->float('death_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turn_report_colonies');
    }
};
