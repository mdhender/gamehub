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
        Schema::create('colony_template_population', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_id')->constrained()->cascadeOnDelete();
            $table->string('population_code');
            $table->integer('quantity');
            $table->float('pay_rate');
            $table->unique(['colony_template_id', 'population_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colony_template_population');
    }
};
