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
        Schema::table('turn_report_colony_population', function (Blueprint $table) {
            $table->integer('employed')->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turn_report_colony_population', function (Blueprint $table) {
            $table->dropColumn('employed');
        });
    }
};
