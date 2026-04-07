<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateColonyFarmTablesMigrationTest extends TestCase
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

    private function insertGroup(int $id, int $colonyId, int $groupNumber = 1): void
    {
        DB::table('colony_farm_groups')->insert([
            'id' => $id,
            'colony_id' => $colonyId,
            'group_number' => $groupNumber,
        ]);
    }

    // ── Farm Groups ─────────────────────────────────────────────────────────

    public function test_farm_groups_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_farm_groups', [
            'id',
            'colony_id',
            'group_number',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_farm_groups', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_farm_groups', 'updated_at'));
    }

    public function test_composite_unique_prevents_duplicate_group_number_per_colony(): void
    {
        $this->insertColony(1);

        DB::table('colony_farm_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
        ]);

        $this->expectException(QueryException::class);

        DB::table('colony_farm_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
        ]);
    }

    public function test_same_group_number_may_exist_on_different_colonies(): void
    {
        $this->insertColony(1);
        $this->insertColony(2);

        DB::table('colony_farm_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
        ]);

        DB::table('colony_farm_groups')->insert([
            'colony_id' => 2,
            'group_number' => 1,
        ]);

        $this->assertDatabaseCount('colony_farm_groups', 2);
    }

    public function test_deleting_a_colony_cascades_to_farm_groups(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        $this->assertDatabaseCount('colony_farm_groups', 1);

        DB::table('colonies')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_farm_groups', 0);
    }

    // ── Farm Units ──────────────────────────────────────────────────────────

    public function test_farm_units_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_farm_units', [
            'id',
            'colony_farm_group_id',
            'unit',
            'tech_level',
            'quantity',
            'stage',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_farm_units', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_farm_units', 'updated_at'));
    }

    public function test_deleting_a_group_cascades_to_farm_units(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        DB::table('colony_farm_units')->insert([
            'colony_farm_group_id' => 1,
            'unit' => 'FRM',
            'tech_level' => 1,
            'quantity' => 100,
            'stage' => 1,
        ]);

        $this->assertDatabaseCount('colony_farm_units', 1);

        DB::table('colony_farm_groups')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_farm_units', 0);
    }

    public function test_deleting_a_colony_cascades_to_farm_units(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        DB::table('colony_farm_units')->insert([
            'colony_farm_group_id' => 1,
            'unit' => 'FRM',
            'tech_level' => 1,
            'quantity' => 100,
            'stage' => 3,
        ]);

        $this->assertDatabaseCount('colony_farm_units', 1);

        DB::table('colonies')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_farm_units', 0);
    }
}
