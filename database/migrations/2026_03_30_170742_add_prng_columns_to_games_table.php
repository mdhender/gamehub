<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('prng_seed')->default('');
            $table->text('prng_state')->nullable();
        });

        // Back-fill existing games with random seeds.
        foreach (DB::table('games')->get() as $game) {
            DB::table('games')->where('id', $game->id)->update(['prng_seed' => Str::random(32)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['prng_seed', 'prng_state']);
        });
    }
};
