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
        Schema::table('colony_templates', function (Blueprint $table) {
            $table->float('sol')->default(0.0);
            $table->float('birth_rate')->default(0.0);
            $table->float('death_rate')->default(0.0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colony_templates', function (Blueprint $table) {
            $table->dropColumn(['sol', 'birth_rate', 'death_rate']);
        });
    }
};
