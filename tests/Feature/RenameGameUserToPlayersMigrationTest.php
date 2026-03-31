<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RenameGameUserToPlayersMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_players_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('players'));
        $this->assertFalse(Schema::hasTable('game_user'));

        foreach (['id', 'game_id', 'user_id', 'role', 'is_active', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('players', $column), "Missing column: {$column}");
        }
    }
}
