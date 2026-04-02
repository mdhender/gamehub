<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateTurnsTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a game row without parent FK records using defer_foreign_keys.
     */
    private function insertGame(int $id): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('games')->insert([
            'id' => $id,
            'name' => 'Test Game',
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('turns', [
            'id',
            'game_id',
            'number',
            'status',
            'reports_locked_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_default_status_is_pending(): void
    {
        $this->insertGame(1);

        DB::table('turns')->insert([
            'game_id' => 1,
            'number' => 0,
        ]);

        $row = DB::table('turns')->first();
        $this->assertSame('pending', $row->status);
    }

    public function test_composite_unique_prevents_duplicate_turn_number_in_same_game(): void
    {
        $this->insertGame(1);

        DB::table('turns')->insert([
            'game_id' => 1,
            'number' => 1,
        ]);

        $this->expectException(QueryException::class);

        DB::table('turns')->insert([
            'game_id' => 1,
            'number' => 1,
        ]);
    }

    public function test_same_turn_number_may_exist_in_different_games(): void
    {
        $this->insertGame(1);
        $this->insertGame(2);

        DB::table('turns')->insert(['game_id' => 1, 'number' => 1]);
        DB::table('turns')->insert(['game_id' => 2, 'number' => 1]);

        $this->assertDatabaseCount('turns', 2);
    }

    public function test_deleting_a_game_cascades_to_turns(): void
    {
        $this->insertGame(1);

        DB::table('turns')->insert([
            'game_id' => 1,
            'number' => 1,
        ]);

        $this->assertDatabaseCount('turns', 1);

        DB::table('games')->where('id', 1)->delete();

        $this->assertDatabaseCount('turns', 0);
    }

    public function test_reports_locked_at_is_nullable(): void
    {
        $this->insertGame(1);

        DB::table('turns')->insert([
            'game_id' => 1,
            'number' => 0,
        ]);

        $row = DB::table('turns')->first();
        $this->assertNull($row->reports_locked_at);
    }
}
