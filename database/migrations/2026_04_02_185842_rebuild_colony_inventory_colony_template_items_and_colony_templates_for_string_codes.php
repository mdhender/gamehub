<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Defer FK enforcement to commit so the INSERT…SELECT from old tables
        // can reference parent rows that may not exist in tests.  Works inside
        // an active transaction (unlike PRAGMA foreign_keys which cannot be
        // changed inside a transaction in SQLite).
        DB::statement('PRAGMA defer_foreign_keys = ON');

        $this->rebuild();
    }

    private function rebuild(): void
    {
        // Preflight: colony_inventory.unit must be 1–30
        $invalid = DB::table('colony_inventory')
            ->whereNotBetween('unit', [1, 30])
            ->first();

        if ($invalid !== null) {
            throw new RuntimeException(
                "colony_inventory.unit contains unknown integer value: {$invalid->unit}"
            );
        }

        // Preflight: colony_template_items.unit must be 1–30
        $invalid = DB::table('colony_template_items')
            ->whereNotBetween('unit', [1, 30])
            ->first();

        if ($invalid !== null) {
            throw new RuntimeException(
                "colony_template_items.unit contains unknown integer value: {$invalid->unit}"
            );
        }

        // Preflight: colony_templates.kind must be 1
        $invalid = DB::table('colony_templates')
            ->where('kind', '!=', 1)
            ->first();

        if ($invalid !== null) {
            throw new RuntimeException(
                "colony_templates.kind contains unknown integer value: {$invalid->kind}"
            );
        }

        // ── Rebuild colony_inventory ──────────────────────────────────────────

        DB::statement('DROP TABLE IF EXISTS colony_inventory_temp');
        DB::statement('
            CREATE TABLE colony_inventory_temp (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                colony_id             INTEGER NOT NULL,
                unit                  VARCHAR NOT NULL,
                tech_level            INTEGER NOT NULL,
                quantity_assembled    INTEGER NOT NULL,
                quantity_disassembled INTEGER NOT NULL,
                FOREIGN KEY (colony_id) REFERENCES colonies (id) ON DELETE CASCADE
            )
        ');

        DB::statement("
            INSERT INTO colony_inventory_temp
                SELECT id, colony_id,
                CASE unit
                    WHEN 1  THEN 'AUT'
                    WHEN 2  THEN 'ESH'
                    WHEN 3  THEN 'EWP'
                    WHEN 4  THEN 'FCT'
                    WHEN 5  THEN 'FRM'
                    WHEN 6  THEN 'HEN'
                    WHEN 7  THEN 'LAB'
                    WHEN 8  THEN 'LFS'
                    WHEN 9  THEN 'MIN'
                    WHEN 10 THEN 'MSL'
                    WHEN 11 THEN 'PWP'
                    WHEN 12 THEN 'SEN'
                    WHEN 13 THEN 'SLS'
                    WHEN 14 THEN 'SPD'
                    WHEN 15 THEN 'STU'
                    WHEN 16 THEN 'ANM'
                    WHEN 17 THEN 'ASC'
                    WHEN 18 THEN 'ASW'
                    WHEN 19 THEN 'MSS'
                    WHEN 20 THEN 'TPT'
                    WHEN 21 THEN 'MTBT'
                    WHEN 22 THEN 'RPV'
                    WHEN 23 THEN 'CNGD'
                    WHEN 24 THEN 'FOOD'
                    WHEN 25 THEN 'FUEL'
                    WHEN 26 THEN 'GOLD'
                    WHEN 27 THEN 'METS'
                    WHEN 28 THEN 'MTSP'
                    WHEN 29 THEN 'NMTS'
                    WHEN 30 THEN 'RSCH'
                END,
                tech_level, quantity_assembled, quantity_disassembled
                FROM colony_inventory
        ");

        Schema::drop('colony_inventory');
        DB::statement('ALTER TABLE colony_inventory_temp RENAME TO colony_inventory');

        // ── Rebuild colony_template_items ─────────────────────────────────────

        DB::statement('DROP TABLE IF EXISTS colony_template_items_temp');
        DB::statement('
            CREATE TABLE colony_template_items_temp (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                colony_template_id    INTEGER NOT NULL,
                unit                  VARCHAR NOT NULL,
                tech_level            INTEGER NOT NULL,
                quantity_assembled    INTEGER NOT NULL,
                quantity_disassembled INTEGER NOT NULL,
                FOREIGN KEY (colony_template_id) REFERENCES colony_templates (id) ON DELETE CASCADE
            )
        ');

        DB::statement("
            INSERT INTO colony_template_items_temp
                SELECT id, colony_template_id,
                CASE unit
                    WHEN 1  THEN 'AUT'
                    WHEN 2  THEN 'ESH'
                    WHEN 3  THEN 'EWP'
                    WHEN 4  THEN 'FCT'
                    WHEN 5  THEN 'FRM'
                    WHEN 6  THEN 'HEN'
                    WHEN 7  THEN 'LAB'
                    WHEN 8  THEN 'LFS'
                    WHEN 9  THEN 'MIN'
                    WHEN 10 THEN 'MSL'
                    WHEN 11 THEN 'PWP'
                    WHEN 12 THEN 'SEN'
                    WHEN 13 THEN 'SLS'
                    WHEN 14 THEN 'SPD'
                    WHEN 15 THEN 'STU'
                    WHEN 16 THEN 'ANM'
                    WHEN 17 THEN 'ASC'
                    WHEN 18 THEN 'ASW'
                    WHEN 19 THEN 'MSS'
                    WHEN 20 THEN 'TPT'
                    WHEN 21 THEN 'MTBT'
                    WHEN 22 THEN 'RPV'
                    WHEN 23 THEN 'CNGD'
                    WHEN 24 THEN 'FOOD'
                    WHEN 25 THEN 'FUEL'
                    WHEN 26 THEN 'GOLD'
                    WHEN 27 THEN 'METS'
                    WHEN 28 THEN 'MTSP'
                    WHEN 29 THEN 'NMTS'
                    WHEN 30 THEN 'RSCH'
                END,
                tech_level, quantity_assembled, quantity_disassembled
                FROM colony_template_items
        ");

        Schema::drop('colony_template_items');
        DB::statement('ALTER TABLE colony_template_items_temp RENAME TO colony_template_items');

        // ── Rebuild colony_templates (kind int→string, drop unique on game_id) ─

        DB::statement('DROP TABLE IF EXISTS colony_templates_temp');
        DB::statement('
            CREATE TABLE colony_templates_temp (
                id         INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                game_id    INTEGER NOT NULL,
                kind       VARCHAR NOT NULL,
                tech_level INTEGER NOT NULL,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE
            )
        ');

        DB::statement("
            INSERT INTO colony_templates_temp
                SELECT id, game_id,
                CASE kind
                    WHEN 1 THEN 'COPN'
                END,
                tech_level, created_at, updated_at
                FROM colony_templates
        ");

        Schema::drop('colony_templates');
        DB::statement('ALTER TABLE colony_templates_temp RENAME TO colony_templates');
    }

    public function down(): void
    {
        // Intentionally left empty — SQLite rebuild migrations are not reversible.
    }
};
