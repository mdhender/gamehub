<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateColonyPopulationTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a colony row without parent FK records using defer_foreign_keys.
     */
    private function insertColony(int $id): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('colonies')->insert([
            'id' => $id,
            'empire_id' => 1,
            'planet_id' => 1,
            'kind' => 'COPN',
            'tech_level' => 1,
            'name' => 'Test Colony',
            'is_on_surface' => 1,
            'rations' => 1.0,
            'sol' => 0.0,
            'birth_rate' => 0.0,
            'death_rate' => 0.0,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_population', [
            'id',
            'colony_id',
            'population_code',
            'quantity',
            'pay_rate',
            'rebel_quantity',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_population', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_population', 'updated_at'));
    }

    public function test_rebel_quantity_defaults_to_zero(): void
    {
        $this->insertColony(1);

        DB::table('colony_population')->insert([
            'colony_id' => 1,
            'population_code' => 'UEM',
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        $row = DB::table('colony_population')->first();
        $this->assertSame(0, (int) $row->rebel_quantity);
    }

    public function test_composite_unique_prevents_duplicate_population_code_per_colony(): void
    {
        $this->insertColony(1);

        DB::table('colony_population')->insert([
            'colony_id' => 1,
            'population_code' => 'USK',
            'quantity' => 50,
            'pay_rate' => 1.0,
        ]);

        $this->expectException(QueryException::class);

        DB::table('colony_population')->insert([
            'colony_id' => 1,
            'population_code' => 'USK',
            'quantity' => 25,
            'pay_rate' => 1.5,
        ]);
    }

    public function test_same_population_code_may_exist_on_different_colonies(): void
    {
        $this->insertColony(1);
        $this->insertColony(2);

        DB::table('colony_population')->insert([
            'colony_id' => 1,
            'population_code' => 'PRO',
            'quantity' => 10,
            'pay_rate' => 2.0,
        ]);

        DB::table('colony_population')->insert([
            'colony_id' => 2,
            'population_code' => 'PRO',
            'quantity' => 20,
            'pay_rate' => 2.0,
        ]);

        $this->assertDatabaseCount('colony_population', 2);
    }

    public function test_deleting_a_colony_cascades_to_population_rows(): void
    {
        $this->insertColony(1);

        DB::table('colony_population')->insert([
            'colony_id' => 1,
            'population_code' => 'SLD',
            'quantity' => 5,
            'pay_rate' => 3.0,
        ]);

        $this->assertDatabaseCount('colony_population', 1);

        DB::table('colonies')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_population', 0);
    }
}
