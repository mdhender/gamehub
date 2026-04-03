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
        Schema::create('turn_report_colony_population', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_report_colony_id')->constrained()->cascadeOnDelete();
            $table->string('population_code');
            $table->integer('quantity');
            $table->float('pay_rate');
            $table->integer('rebel_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turn_report_colony_population');
    }
};
