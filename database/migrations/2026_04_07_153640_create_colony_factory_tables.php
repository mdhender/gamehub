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
        Schema::create('colony_template_factory_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_id')->constrained()->cascadeOnDelete();
            $table->integer('group_number');
            $table->string('orders_unit');
            $table->integer('orders_tech_level')->default(0);
            $table->string('pending_orders_unit')->nullable();
            $table->integer('pending_orders_tech_level')->nullable();
            $table->unique(['colony_template_id', 'group_number']);
        });

        Schema::create('colony_template_factory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_factory_group_id')->constrained('colony_template_factory_groups')->cascadeOnDelete();
            $table->string('unit');
            $table->integer('tech_level');
            $table->integer('quantity');
        });

        Schema::create('colony_template_factory_wip', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_template_factory_group_id')->constrained('colony_template_factory_groups')->cascadeOnDelete();
            $table->integer('quarter');
            $table->string('unit');
            $table->integer('tech_level')->default(0);
            $table->integer('quantity');
            $table->unique(['colony_template_factory_group_id', 'quarter']);
        });

        Schema::create('colony_factory_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_id')->constrained()->cascadeOnDelete();
            $table->integer('group_number');
            $table->string('orders_unit');
            $table->integer('orders_tech_level')->default(0);
            $table->string('pending_orders_unit')->nullable();
            $table->integer('pending_orders_tech_level')->nullable();
            $table->float('input_remainder_mets')->default(0);
            $table->float('input_remainder_nmts')->default(0);
            $table->unique(['colony_id', 'group_number']);
        });

        Schema::create('colony_factory_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_factory_group_id')->constrained('colony_factory_groups')->cascadeOnDelete();
            $table->string('unit');
            $table->integer('tech_level');
            $table->integer('quantity');
        });

        Schema::create('colony_factory_wip', function (Blueprint $table) {
            $table->id();
            $table->foreignId('colony_factory_group_id')->constrained('colony_factory_groups')->cascadeOnDelete();
            $table->integer('quarter');
            $table->string('unit');
            $table->integer('tech_level')->default(0);
            $table->integer('quantity');
            $table->unique(['colony_factory_group_id', 'quarter']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colony_factory_wip');
        Schema::dropIfExists('colony_factory_units');
        Schema::dropIfExists('colony_factory_groups');
        Schema::dropIfExists('colony_template_factory_wip');
        Schema::dropIfExists('colony_template_factory_units');
        Schema::dropIfExists('colony_template_factory_groups');
    }
};
