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
        Schema::table('colonies', function (Blueprint $table) {
            $table->index('empire_id');
            $table->index('star_id');
            $table->index('planet_id');
        });

        Schema::table('colony_inventory', function (Blueprint $table) {
            $table->index('colony_id');
        });

        Schema::table('colony_template_items', function (Blueprint $table) {
            $table->index('colony_template_id');
        });

        Schema::table('colony_templates', function (Blueprint $table) {
            $table->index('game_id');
        });

        Schema::table('games', function (Blueprint $table) {
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colonies', function (Blueprint $table) {
            $table->dropIndex(['empire_id']);
            $table->dropIndex(['star_id']);
            $table->dropIndex(['planet_id']);
        });

        Schema::table('colony_inventory', function (Blueprint $table) {
            $table->dropIndex(['colony_id']);
        });

        Schema::table('colony_template_items', function (Blueprint $table) {
            $table->dropIndex(['colony_template_id']);
        });

        Schema::table('colony_templates', function (Blueprint $table) {
            $table->dropIndex(['game_id']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
