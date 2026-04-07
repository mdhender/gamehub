<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateColonyFactoryTablesMigrationTest extends TestCase
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
        DB::table('colony_factory_groups')->insert([
            'id' => $id,
            'colony_id' => $colonyId,
            'group_number' => $groupNumber,
            'orders_unit' => 'FCT',
            'orders_tech_level' => 1,
        ]);
    }

    // ── Factory Groups ───────────────────────────────────────────────────────

    public function test_factory_groups_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_factory_groups', [
            'id',
            'colony_id',
            'group_number',
            'orders_unit',
            'orders_tech_level',
            'pending_orders_unit',
            'pending_orders_tech_level',
            'input_remainder_mets',
            'input_remainder_nmts',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_factory_groups', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_factory_groups', 'updated_at'));
    }

    public function test_orders_tech_level_defaults_to_zero(): void
    {
        $this->insertColony(1);

        DB::table('colony_factory_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'orders_unit' => 'FCT',
        ]);

        $row = DB::table('colony_factory_groups')->first();
        $this->assertSame(0, (int) $row->orders_tech_level);
    }

    public function test_pending_orders_default_to_null(): void
    {
        $this->insertColony(1);

        DB::table('colony_factory_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'orders_unit' => 'FCT',
        ]);

        $row = DB::table('colony_factory_groups')->first();
        $this->assertNull($row->pending_orders_unit);
        $this->assertNull($row->pending_orders_tech_level);
    }

    public function test_remainder_fields_default_to_zero(): void
    {
        $this->insertColony(1);

        DB::table('colony_factory_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'orders_unit' => 'FCT',
        ]);

        $row = DB::table('colony_factory_groups')->first();
        $this->assertSame(0.0, (float) $row->input_remainder_mets);
        $this->assertSame(0.0, (float) $row->input_remainder_nmts);
    }

    public function test_composite_unique_prevents_duplicate_group_number_per_colony(): void
    {
        $this->insertColony(1);

        DB::table('colony_factory_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'orders_unit' => 'FCT',
        ]);

        $this->expectException(QueryException::class);

        DB::table('colony_factory_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'orders_unit' => 'AUT',
        ]);
    }

    public function test_same_group_number_may_exist_on_different_colonies(): void
    {
        $this->insertColony(1);
        $this->insertColony(2);

        DB::table('colony_factory_groups')->insert([
            'colony_id' => 1,
            'group_number' => 1,
            'orders_unit' => 'FCT',
        ]);

        DB::table('colony_factory_groups')->insert([
            'colony_id' => 2,
            'group_number' => 1,
            'orders_unit' => 'FCT',
        ]);

        $this->assertDatabaseCount('colony_factory_groups', 2);
    }

    public function test_deleting_a_colony_cascades_to_factory_groups(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        $this->assertDatabaseCount('colony_factory_groups', 1);

        DB::table('colonies')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_factory_groups', 0);
    }

    // ── Factory Units ────────────────────────────────────────────────────────

    public function test_factory_units_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_factory_units', [
            'id',
            'colony_factory_group_id',
            'unit',
            'tech_level',
            'quantity',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_factory_units', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_factory_units', 'updated_at'));
    }

    public function test_deleting_a_group_cascades_to_factory_units(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        DB::table('colony_factory_units')->insert([
            'colony_factory_group_id' => 1,
            'unit' => 'FCT',
            'tech_level' => 1,
            'quantity' => 5,
        ]);

        $this->assertDatabaseCount('colony_factory_units', 1);

        DB::table('colony_factory_groups')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_factory_units', 0);
    }

    public function test_deleting_a_colony_cascades_to_factory_units(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        DB::table('colony_factory_units')->insert([
            'colony_factory_group_id' => 1,
            'unit' => 'FCT',
            'tech_level' => 1,
            'quantity' => 5,
        ]);

        $this->assertDatabaseCount('colony_factory_units', 1);

        DB::table('colonies')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_factory_units', 0);
    }

    // ── Factory WIP ──────────────────────────────────────────────────────────

    public function test_factory_wip_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_factory_wip', [
            'id',
            'colony_factory_group_id',
            'quarter',
            'unit',
            'tech_level',
            'quantity',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_factory_wip', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_factory_wip', 'updated_at'));
    }

    public function test_wip_tech_level_defaults_to_zero(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        DB::table('colony_factory_wip')->insert([
            'colony_factory_group_id' => 1,
            'quarter' => 1,
            'unit' => 'FCT',
            'quantity' => 3,
        ]);

        $row = DB::table('colony_factory_wip')->first();
        $this->assertSame(0, (int) $row->tech_level);
    }

    public function test_composite_unique_prevents_duplicate_quarter_per_group(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        DB::table('colony_factory_wip')->insert([
            'colony_factory_group_id' => 1,
            'quarter' => 1,
            'unit' => 'FCT',
            'quantity' => 3,
        ]);

        $this->expectException(QueryException::class);

        DB::table('colony_factory_wip')->insert([
            'colony_factory_group_id' => 1,
            'quarter' => 1,
            'unit' => 'AUT',
            'quantity' => 2,
        ]);
    }

    public function test_same_quarter_may_exist_on_different_groups(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1, groupNumber: 1);
        $this->insertGroup(2, colonyId: 1, groupNumber: 2);

        DB::table('colony_factory_wip')->insert([
            'colony_factory_group_id' => 1,
            'quarter' => 1,
            'unit' => 'FCT',
            'quantity' => 3,
        ]);

        DB::table('colony_factory_wip')->insert([
            'colony_factory_group_id' => 2,
            'quarter' => 1,
            'unit' => 'FCT',
            'quantity' => 2,
        ]);

        $this->assertDatabaseCount('colony_factory_wip', 2);
    }

    public function test_deleting_a_group_cascades_to_factory_wip(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        DB::table('colony_factory_wip')->insert([
            'colony_factory_group_id' => 1,
            'quarter' => 1,
            'unit' => 'FCT',
            'quantity' => 3,
        ]);

        $this->assertDatabaseCount('colony_factory_wip', 1);

        DB::table('colony_factory_groups')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_factory_wip', 0);
    }

    public function test_deleting_a_colony_cascades_to_factory_wip(): void
    {
        $this->insertColony(1);
        $this->insertGroup(1, colonyId: 1);

        DB::table('colony_factory_wip')->insert([
            'colony_factory_group_id' => 1,
            'quarter' => 1,
            'unit' => 'FCT',
            'quantity' => 3,
        ]);

        $this->assertDatabaseCount('colony_factory_wip', 1);

        DB::table('colonies')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_factory_wip', 0);
    }
}
