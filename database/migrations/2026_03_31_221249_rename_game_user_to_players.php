<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Production has a `game_user` table from the original migration. That migration
     * was edited in-place to create `players` instead, so production needs this
     * forward-fix to create `players` from `game_user` data.
     */
    public function up(): void
    {
        if (! Schema::hasTable('game_user')) {
            return;
        }

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['game_id', 'user_id']);
            $table->index('user_id');
        });

        DB::table('players')->insertUsing(
            ['game_id', 'user_id', 'role'],
            DB::table('game_user')->select('game_id', 'user_id', 'role')
        );

        Schema::drop('game_user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('players')) {
            return;
        }

        Schema::create('game_user', function (Blueprint $table) {
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->primary(['game_id', 'user_id']);
        });

        DB::table('game_user')->insertUsing(
            ['game_id', 'user_id', 'role'],
            DB::table('players')->select('game_id', 'user_id', 'role')
        );

        Schema::drop('players');
    }
};
