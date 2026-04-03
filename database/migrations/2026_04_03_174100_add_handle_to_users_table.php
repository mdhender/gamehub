<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('handle')->after('name')->default('');
        });

        DB::table('users')->orderBy('id')->each(function (object $user) {
            $firstWord = mb_strtolower(explode(' ', $user->name)[0]);
            $scrubbed = preg_replace("/[^a-z0-9'_-]/u", '', $firstWord);
            $handle = $scrubbed ?: 'user';

            if (DB::table('users')->where('handle', $handle)->where('id', '!=', $user->id)->exists()) {
                $handle = $handle.$user->id;
            }

            DB::table('users')->where('id', $user->id)->update(['handle' => $handle]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('handle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['handle']);
            $table->dropColumn('handle');
        });
    }
};
