<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');

        $this->rebuild();
    }

    private function rebuild(): void
    {
        DB::statement('DROP TABLE IF EXISTS colonies_temp');
        DB::statement("
            CREATE TABLE colonies_temp (
                id            INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                empire_id     INTEGER NOT NULL,
                star_id       INTEGER NOT NULL,
                planet_id     INTEGER,
                kind          VARCHAR NOT NULL,
                tech_level    INTEGER NOT NULL,
                name          VARCHAR NOT NULL DEFAULT 'Not Named',
                rations       REAL NOT NULL DEFAULT 1.0,
                sol           REAL NOT NULL DEFAULT 0.0,
                birth_rate    REAL NOT NULL DEFAULT 0.0,
                death_rate    REAL NOT NULL DEFAULT 0.0,
                FOREIGN KEY (empire_id) REFERENCES empires (id) ON DELETE CASCADE,
                FOREIGN KEY (star_id) REFERENCES stars (id) ON DELETE CASCADE,
                FOREIGN KEY (planet_id) REFERENCES planets (id) ON DELETE CASCADE
            )
        ");

        DB::statement('
            INSERT INTO colonies_temp
                (id, empire_id, star_id, planet_id, kind, tech_level,
                 name, rations, sol, birth_rate, death_rate)
            SELECT c.id, c.empire_id, p.star_id, c.planet_id, c.kind, c.tech_level,
                c.name, c.rations, c.sol, c.birth_rate, c.death_rate
            FROM colonies c
            INNER JOIN planets p ON p.id = c.planet_id
        ');

        Schema::drop('colonies');
        DB::statement('ALTER TABLE colonies_temp RENAME TO colonies');
    }

    public function down(): void
    {
        // Intentionally left empty — SQLite rebuild migrations are not reversible.
    }
};
