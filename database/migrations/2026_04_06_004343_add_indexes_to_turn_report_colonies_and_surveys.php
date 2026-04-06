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
        Schema::table('turn_report_colonies', function (Blueprint $table) {
            $table->index('source_colony_id');
            $table->index('planet_id');
        });

        Schema::table('turn_report_surveys', function (Blueprint $table) {
            $table->index('planet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turn_report_colonies', function (Blueprint $table) {
            $table->dropIndex(['source_colony_id']);
            $table->dropIndex(['planet_id']);
        });

        Schema::table('turn_report_surveys', function (Blueprint $table) {
            $table->dropIndex(['planet_id']);
        });
    }
};
