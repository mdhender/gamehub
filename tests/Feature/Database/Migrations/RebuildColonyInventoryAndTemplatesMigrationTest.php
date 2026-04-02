<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RebuildColonyInventoryAndTemplatesMigrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Drop the three tables and recreate them with the legacy integer schema
     * (no FK constraints so test data can be inserted freely).
     */
    private function rebuildOldSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::statement('DROP TABLE IF EXISTS colony_template_items');
        DB::statement('DROP TABLE IF EXISTS colony_inventory');
        DB::statement('DROP TABLE IF EXISTS colony_templates');

        DB::statement('
            CREATE TABLE colony_templates (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                game_id    INTEGER NOT NULL,
                kind       INTEGER NOT NULL,
                tech_level INTEGER NOT NULL,
                created_at DATETIME,
                updated_at DATETIME
            )
        ');

        DB::statement('
            CREATE TABLE colony_inventory (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                colony_id             INTEGER NOT NULL,
                unit                  INTEGER NOT NULL,
                tech_level            INTEGER NOT NULL,
                quantity_assembled    INTEGER NOT NULL,
                quantity_disassembled INTEGER NOT NULL
            )
        ');

        DB::statement('
            CREATE TABLE colony_template_items (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                colony_template_id    INTEGER NOT NULL,
                unit                  INTEGER NOT NULL,
                tech_level            INTEGER NOT NULL,
                quantity_assembled    INTEGER NOT NULL,
                quantity_disassembled INTEGER NOT NULL
            )
        ');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * @return object{up(): void}
     */
    private function getMigration(): object
    {
        $files = glob(database_path('migrations/*rebuild_colony_inventory_colony_template_items_and_colony_templates*.php'));
        sort($files);

        return require $files[0];
    }

    // ── Data-conversion tests (require old schema + direct migration call) ────

    public function test_migrates_known_unit_ints_to_string_codes_in_colony_inventory(): void
    {
        $this->rebuildOldSchema();

        DB::table('colony_inventory')->insert([
            ['colony_id' => 1, 'unit' => 1,  'tech_level' => 1, 'quantity_assembled' => 10, 'quantity_disassembled' => 0],
            ['colony_id' => 1, 'unit' => 15, 'tech_level' => 1, 'quantity_assembled' => 5,  'quantity_disassembled' => 2],
            ['colony_id' => 1, 'unit' => 16, 'tech_level' => 1, 'quantity_assembled' => 1,  'quantity_disassembled' => 0],
            ['colony_id' => 1, 'unit' => 21, 'tech_level' => 1, 'quantity_assembled' => 3,  'quantity_disassembled' => 0],
            ['colony_id' => 1, 'unit' => 23, 'tech_level' => 1, 'quantity_assembled' => 100, 'quantity_disassembled' => 0],
            ['colony_id' => 1, 'unit' => 30, 'tech_level' => 1, 'quantity_assembled' => 50, 'quantity_disassembled' => 0],
        ]);

        $this->getMigration()->up();

        $rows = DB::table('colony_inventory')->orderBy('id')->get();

        $this->assertSame('AUT', $rows[0]->unit);
        $this->assertSame('STU', $rows[1]->unit);
        $this->assertSame('ANM', $rows[2]->unit);
        $this->assertSame('MTBT', $rows[3]->unit);
        $this->assertSame('CNGD', $rows[4]->unit);
        $this->assertSame('RSCH', $rows[5]->unit);
    }

    public function test_migrates_known_unit_ints_to_string_codes_in_colony_template_items(): void
    {
        $this->rebuildOldSchema();

        DB::table('colony_template_items')->insert([
            ['colony_template_id' => 1, 'unit' => 4,  'tech_level' => 1, 'quantity_assembled' => 2, 'quantity_disassembled' => 0],
            ['colony_template_id' => 1, 'unit' => 9,  'tech_level' => 1, 'quantity_assembled' => 5, 'quantity_disassembled' => 0],
            ['colony_template_id' => 1, 'unit' => 20, 'tech_level' => 1, 'quantity_assembled' => 1, 'quantity_disassembled' => 0],
            ['colony_template_id' => 1, 'unit' => 22, 'tech_level' => 1, 'quantity_assembled' => 3, 'quantity_disassembled' => 0],
            ['colony_template_id' => 1, 'unit' => 26, 'tech_level' => 1, 'quantity_assembled' => 0, 'quantity_disassembled' => 10],
        ]);

        $this->getMigration()->up();

        $rows = DB::table('colony_template_items')->orderBy('id')->get();

        $this->assertSame('FCT', $rows[0]->unit);
        $this->assertSame('MIN', $rows[1]->unit);
        $this->assertSame('TPT', $rows[2]->unit);
        $this->assertSame('RPV', $rows[3]->unit);
        $this->assertSame('GOLD', $rows[4]->unit);
    }

    public function test_migrates_colony_templates_kind_from_1_to_copn(): void
    {
        $this->rebuildOldSchema();

        DB::table('colony_templates')->insert([
            'game_id' => 1, 'kind' => 1, 'tech_level' => 1,
        ]);

        $this->getMigration()->up();

        $row = DB::table('colony_templates')->first();
        $this->assertSame('COPN', $row->kind);
    }

    // ── Structural tests (post-RefreshDatabase state) ─────────────────────────

    public function test_drops_unique_constraint_on_colony_templates_game_id(): void
    {
        // defer_foreign_keys works inside a transaction; disableForeignKeyConstraints does not
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('colony_templates')->insert(['game_id' => 1, 'kind' => 'COPN', 'tech_level' => 1]);
        DB::table('colony_templates')->insert(['game_id' => 1, 'kind' => 'CENC', 'tech_level' => 1]);

        $this->assertDatabaseCount('colony_templates', 2);
    }

    public function test_preserves_foreign_keys(): void
    {
        $fks = collect(DB::select('PRAGMA foreign_key_list(colony_inventory)'));
        $this->assertCount(1, $fks);
        $this->assertSame('colonies', $fks[0]->table);
        $this->assertSame('id', $fks[0]->to);
        $this->assertSame('colony_id', $fks[0]->from);
        $this->assertSame('CASCADE', strtoupper($fks[0]->on_delete));

        $fks = collect(DB::select('PRAGMA foreign_key_list(colony_template_items)'));
        $this->assertCount(1, $fks);
        $this->assertSame('colony_templates', $fks[0]->table);
        $this->assertSame('id', $fks[0]->to);
        $this->assertSame('colony_template_id', $fks[0]->from);
        $this->assertSame('CASCADE', strtoupper($fks[0]->on_delete));

        $fks = collect(DB::select('PRAGMA foreign_key_list(colony_templates)'));
        $this->assertCount(1, $fks);
        $this->assertSame('games', $fks[0]->table);
        $this->assertSame('id', $fks[0]->to);
        $this->assertSame('game_id', $fks[0]->from);
        $this->assertSame('CASCADE', strtoupper($fks[0]->on_delete));
    }

    // ── Fail-fast tests ───────────────────────────────────────────────────────

    public function test_fails_fast_on_unknown_unit_integer_in_colony_inventory(): void
    {
        $this->rebuildOldSchema();

        DB::table('colony_inventory')->insert([
            'colony_id' => 1, 'unit' => 99, 'tech_level' => 1,
            'quantity_assembled' => 0, 'quantity_disassembled' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/colony_inventory/');

        $this->getMigration()->up();
    }

    public function test_fails_fast_on_unknown_template_kind(): void
    {
        $this->rebuildOldSchema();

        DB::table('colony_templates')->insert([
            'game_id' => 1, 'kind' => 2, 'tech_level' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/colony_templates/');

        $this->getMigration()->up();
    }
}
