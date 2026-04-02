<?php

namespace Tests\Feature\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RebuildColoniesTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Drop the colonies table and recreate it with the legacy integer schema
     * (no FK constraints so test data can be inserted freely).
     */
    private function rebuildOldSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::statement('DROP TABLE IF EXISTS colonies');

        DB::statement('
            CREATE TABLE colonies (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                empire_id  INTEGER NOT NULL,
                planet_id  INTEGER NOT NULL,
                kind       INTEGER NOT NULL,
                tech_level INTEGER NOT NULL
            )
        ');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * @return object{up(): void}
     */
    private function getMigration(): object
    {
        $files = glob(database_path('migrations/*rebuild_colonies_for_string_kind_and_setup_report_columns*.php'));
        sort($files);

        return require $files[0];
    }

    // ── Data-conversion tests (require old schema + direct migration call) ────

    public function test_migrates_legacy_kind_1_to_copn(): void
    {
        $this->rebuildOldSchema();

        DB::table('colonies')->insert([
            'empire_id' => 1, 'planet_id' => 1, 'kind' => 1, 'tech_level' => 1,
        ]);

        $this->getMigration()->up();

        $row = DB::table('colonies')->first();
        $this->assertSame('COPN', $row->kind);
    }

    public function test_adds_six_new_columns_with_correct_defaults(): void
    {
        $this->rebuildOldSchema();

        DB::table('colonies')->insert([
            'empire_id' => 1, 'planet_id' => 1, 'kind' => 1, 'tech_level' => 2,
        ]);

        $this->getMigration()->up();

        $row = DB::table('colonies')->first();
        $this->assertSame('Not Named', $row->name);
        $this->assertSame(1, (int) $row->is_on_surface);
        $this->assertSame(1.0, (float) $row->rations);
        $this->assertSame(0.0, (float) $row->sol);
        $this->assertSame(0.0, (float) $row->birth_rate);
        $this->assertSame(0.0, (float) $row->death_rate);
    }

    public function test_preserves_empire_id_planet_id_tech_level_and_primary_key(): void
    {
        $this->rebuildOldSchema();

        DB::table('colonies')->insert([
            'empire_id' => 42, 'planet_id' => 99, 'kind' => 1, 'tech_level' => 3,
        ]);

        $this->getMigration()->up();

        $row = DB::table('colonies')->first();
        $this->assertSame(1, (int) $row->id);
        $this->assertSame(42, (int) $row->empire_id);
        $this->assertSame(99, (int) $row->planet_id);
        $this->assertSame(3, (int) $row->tech_level);
    }

    // ── Structural test (post-RefreshDatabase state) ──────────────────────────

    public function test_preserves_both_foreign_keys(): void
    {
        $fks = collect(DB::select("PRAGMA foreign_key_list('colonies')"));
        $this->assertCount(2, $fks);

        $byColumn = $fks->keyBy('from');

        $this->assertArrayHasKey('empire_id', $byColumn->toArray());
        $this->assertSame('empires', $byColumn['empire_id']->table);
        $this->assertSame('id', $byColumn['empire_id']->to);
        $this->assertSame('CASCADE', strtoupper($byColumn['empire_id']->on_delete));

        $this->assertArrayHasKey('planet_id', $byColumn->toArray());
        $this->assertSame('planets', $byColumn['planet_id']->table);
        $this->assertSame('id', $byColumn['planet_id']->to);
        $this->assertSame('CASCADE', strtoupper($byColumn['planet_id']->on_delete));
    }

    // ── Fail-fast test ────────────────────────────────────────────────────────

    public function test_fails_fast_on_unknown_legacy_kind(): void
    {
        $this->rebuildOldSchema();

        DB::table('colonies')->insert([
            'empire_id' => 1, 'planet_id' => 1, 'kind' => 2, 'tech_level' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/colonies/');

        $this->getMigration()->up();
    }
}
