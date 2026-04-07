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
        Schema::create('colony_template_farm_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_id')->constrained()->cascadeOnDelete();
            $table->integer('group_number');
            $table->unique(['colony_template_id', 'group_number']);
        });

        Schema::create('colony_template_farm_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_farm_group_id')->constrained('colony_template_farm_groups')->cascadeOnDelete();
            $table->string('unit');
            $table->integer('tech_level');
            $table->integer('quantity');
            $table->integer('stage');
        });

        Schema::create('colony_farm_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_id')->constrained()->cascadeOnDelete();
            $table->integer('group_number');
            $table->unique(['colony_id', 'group_number']);
        });

        Schema::create('colony_farm_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_farm_group_id')->constrained('colony_farm_groups')->cascadeOnDelete();
            $table->string('unit');
            $table->integer('tech_level');
            $table->integer('quantity');
            $table->integer('stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colony_farm_units');
        Schema::dropIfExists('colony_farm_groups');
        Schema::dropIfExists('colony_template_farm_units');
        Schema::dropIfExists('colony_template_farm_groups');
    }
};
