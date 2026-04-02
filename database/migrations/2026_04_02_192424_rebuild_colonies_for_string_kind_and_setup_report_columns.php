<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // defer_foreign_keys works inside transactions; foreign_keys pragma does not
        DB::statement('PRAGMA defer_foreign_keys = ON');

        try {
            $this->rebuild();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function rebuild(): void
    {
        // Preflight: colonies.kind must be 1
        $invalid = DB::table('colonies')
            ->where('kind', '!=', 1)
            ->first();

        if ($invalid !== null) {
            throw new RuntimeException(
                "colonies.kind contains unknown integer value: {$invalid->kind}"
            );
        }

        // ── Rebuild colonies ──────────────────────────────────────────────────

        DB::statement('DROP TABLE IF EXISTS colonies_temp');
        DB::statement("
            CREATE TABLE colonies_temp (
                id            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                empire_id     INTEGER NOT NULL,
                planet_id     INTEGER NOT NULL,
                kind          VARCHAR NOT NULL,
                tech_level    INTEGER NOT NULL,
                name          VARCHAR NOT NULL DEFAULT 'Not Named',
                is_on_surface INTEGER NOT NULL DEFAULT 1,
                rations       REAL NOT NULL DEFAULT 1.0,
                sol           REAL NOT NULL DEFAULT 0.0,
                birth_rate    REAL NOT NULL DEFAULT 0.0,
                death_rate    REAL NOT NULL DEFAULT 0.0,
                FOREIGN KEY (empire_id) REFERENCES empires (id) ON DELETE CASCADE,
                FOREIGN KEY (planet_id) REFERENCES planets (id) ON DELETE CASCADE
            )
        ");

        DB::statement("
            INSERT INTO colonies_temp
                (id, empire_id, planet_id, kind, tech_level,
                 name, is_on_surface, rations, sol, birth_rate, death_rate)
            SELECT id, empire_id, planet_id,
                CASE kind
                    WHEN 1 THEN 'COPN'
                END,
                tech_level,
                'Not Named',
                1,
                1.0,
                0.0,
                0.0,
                0.0
            FROM colonies
        ");

        Schema::drop('colonies');
        DB::statement('ALTER TABLE colonies_temp RENAME TO colonies');
    }

    public function down(): void
    {
        // Intentionally left empty — SQLite rebuild migrations are not reversible.
    }
};
