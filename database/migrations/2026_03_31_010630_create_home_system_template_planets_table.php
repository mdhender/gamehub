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
        Schema::create('home_system_template_planets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_system_template_id')->constrained()->cascadeOnDelete();
            $table->integer('orbit');
            $table->string('type');
            $table->integer('habitability');
            $table->boolean('is_homeworld')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_system_template_planets');
    }
};
