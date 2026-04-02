<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CreateColonyTemplatePopulationTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a colony_template row without parent FK records using defer_foreign_keys.
     */
    private function insertTemplate(int $id, int $gameId = 1): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('colony_templates')->insert([
            'id' => $id,
            'game_id' => $gameId,
            'kind' => 'COPN',
            'tech_level' => 1,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('colony_template_population', [
            'id',
            'colony_template_id',
            'population_code',
            'quantity',
            'pay_rate',
        ]));

        $this->assertFalse(Schema::hasColumn('colony_template_population', 'created_at'));
        $this->assertFalse(Schema::hasColumn('colony_template_population', 'updated_at'));
    }

    public function test_composite_unique_prevents_duplicate_population_code_per_template(): void
    {
        $this->insertTemplate(1);

        DB::table('colony_template_population')->insert([
            'colony_template_id' => 1,
            'population_code' => 'USK',
            'quantity' => 50,
            'pay_rate' => 1.0,
        ]);

        $this->expectException(QueryException::class);

        DB::table('colony_template_population')->insert([
            'colony_template_id' => 1,
            'population_code' => 'USK',
            'quantity' => 25,
            'pay_rate' => 1.5,
        ]);
    }

    public function test_same_population_code_may_exist_on_different_templates(): void
    {
        $this->insertTemplate(1);
        $this->insertTemplate(2);

        DB::table('colony_template_population')->insert([
            'colony_template_id' => 1,
            'population_code' => 'PRO',
            'quantity' => 10,
            'pay_rate' => 2.0,
        ]);

        DB::table('colony_template_population')->insert([
            'colony_template_id' => 2,
            'population_code' => 'PRO',
            'quantity' => 20,
            'pay_rate' => 2.0,
        ]);

        $this->assertDatabaseCount('colony_template_population', 2);
    }

    public function test_deleting_a_template_cascades_to_population_rows(): void
    {
        $this->insertTemplate(1);

        DB::table('colony_template_population')->insert([
            'colony_template_id' => 1,
            'population_code' => 'SLD',
            'quantity' => 5,
            'pay_rate' => 3.0,
        ]);

        $this->assertDatabaseCount('colony_template_population', 1);

        DB::table('colony_templates')->where('id', 1)->delete();

        $this->assertDatabaseCount('colony_template_population', 0);
    }

    public function test_multiple_templates_can_exist_for_one_game(): void
    {
        $this->insertTemplate(1, gameId: 1);
        $this->insertTemplate(2, gameId: 1);

        $this->assertDatabaseCount('colony_templates', 2);
    }
}
