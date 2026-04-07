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
        Schema::create('colony_template_mine_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_id')->constrained()->cascadeOnDelete();
            $table->integer('group_number');
            $table->integer('deposit_id')->nullable();
            $table->unique(['colony_template_id', 'group_number']);
        });

        Schema::create('colony_template_mine_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_mine_group_id')->constrained('colony_template_mine_groups')->cascadeOnDelete();
            $table->string('unit');
            $table->integer('tech_level');
            $table->integer('quantity');
        });

        Schema::create('colony_mine_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_id')->constrained()->cascadeOnDelete();
            $table->integer('group_number');
            $table->foreignId('deposit_id')->constrained('deposits');
            $table->unique(['colony_id', 'group_number']);
        });

        Schema::create('colony_mine_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_mine_group_id')->constrained('colony_mine_groups')->cascadeOnDelete();
            $table->string('unit');
            $table->integer('tech_level');
            $table->integer('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colony_mine_units');
        Schema::dropIfExists('colony_mine_groups');
        Schema::dropIfExists('colony_template_mine_units');
        Schema::dropIfExists('colony_template_mine_groups');
    }
};
