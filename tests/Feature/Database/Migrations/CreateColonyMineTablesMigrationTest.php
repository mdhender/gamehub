<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateColonyMineTablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function insertColony(int $id): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('colonies')->insert([
            'id' => $id,
            'empire_id' => 1,
            'star_id' => 1,
            'planet_id' => 1,
            'kind' => 'COPN',
            'tech_level' => 1,
            'name' => 'Test Colony',
            'rations' => 1.0,
            'sol' => 0.0,
            'birth_rate' => 0.0,
            'death_rate' => 0.0,
        ]);
    }

    private function insertDeposit(int $id): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('deposits')->insert([
            'id' => $id,
            'game_id' => 1,
            'planet_id' => 1,
            'resource' => 'FUEL',
            'yield_pct' => 50.0,
            'quantity_remaining' => 1000,
        ]);
    }

    private function insertGroup(int $id, int $colonyId, int $depositId, int $groupNumber = 1): void
    {
        DB::table('colony_mine_groups')->insert([
            'id' => $id,
            'colony_id' => $colonyId,
            'group_number' => $groupNumber,
            'deposit_id' => $depositId,
        ]);
    }

    // ── Mine Groups ─────────────────────────────────────────────────────────

    public function test_mine_groups_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_mine_groups', [
            'id',
            'colony_id',
            'group_number',
            'deposit_id',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_mine_groups', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_mine_groups', 'updated_at'));
    }

    public function test_deposit_id_is_required(): void
    {
        $this->insertColony(1);

        $this->expectException(QueryException::class);

        DB::table('colony_mine_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            // deposit_id omitted — should fail
        ]);
    }

    public function test_composite_unique_prevents_duplicate_group_number_per_colony(): void
    {
        $this->insertColony(1);
        $this->insertDeposit(1);
        $this->insertDeposit(2);

        DB::table('colony_mine_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'deposit_id' => 1,
        ]);

        $this->expectException(QueryException::class);

        DB::table('colony_mine_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'deposit_id' => 2,
        ]);
    }

    public function test_same_group_number_may_exist_on_different_colonies(): void
    {
        $this->insertColony(1);
        $this->insertColony(2);
        $this->insertDeposit(1);

        DB::table('colony_mine_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'deposit_id' => 1,
        ]);

        DB::table('colony_mine_groups')->insert([
            'colony_id' => 2,
            'group_number' => 1,
            'deposit_id' => 1,
        ]);

        $this->assertDatabaseCount('colony_mine_groups', 2);
    }

    public function test_deleting_a_colony_cascades_to_mine_groups(): void
    {
        $this->insertColony(1);
        $this->insertDeposit(1);
        $this->insertGroup(1, colonyId: 1, depositId: 1);

        $this->assertDatabaseCount('colony_mine_groups', 1);

        DB::table('colonies')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_mine_groups', 0);
    }

    // ── Mine Units ──────────────────────────────────────────────────────────

    public function test_mine_units_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_mine_units', [
            'id',
            'colony_mine_group_id',
            'unit',
            'tech_level',
            'quantity',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_mine_units', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_mine_units', 'updated_at'));
    }

    public function test_deleting_a_group_cascades_to_mine_units(): void
    {
        $this->insertColony(1);
        $this->insertDeposit(1);
        $this->insertGroup(1, colonyId: 1, depositId: 1);

        DB::table('colony_mine_units')->insert([
            'colony_mine_group_id' => 1,
            'unit' => 'MIN',
            'tech_level' => 1,
            'quantity' => 100,
        ]);

        $this->assertDatabaseCount('colony_mine_units', 1);

        DB::table('colony_mine_groups')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_mine_units', 0);
    }

    public function test_deleting_a_colony_cascades_to_mine_units(): void
    {
        $this->insertColony(1);
        $this->insertDeposit(1);
        $this->insertGroup(1, colonyId: 1, depositId: 1);

        DB::table('colony_mine_units')->insert([
            'colony_mine_group_id' => 1,
            'unit' => 'MIN',
            'tech_level' => 1,
            'quantity' => 100,
        ]);

        $this->assertDatabaseCount('colony_mine_units', 1);

        DB::table('colonies')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_mine_units', 0);
    }
}
