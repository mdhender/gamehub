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
        Schema::create('home_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('star_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('homeworld_planet_id')->constrained('planets')->cascadeOnDelete();
            $table->integer('queue_position');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['game_id', 'queue_position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_systems');
    }
};
