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
        Schema::create('turn_report_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_report_id')->constrained()->cascadeOnDelete();
            $table->integer('planet_id')->nullable();
            $table->integer('orbit');
            $table->integer('star_x');
            $table->integer('star_y');
            $table->integer('star_z');
            $table->integer('star_sequence');
            $table->string('planet_type');
            $table->integer('habitability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turn_report_surveys');
    }
};
