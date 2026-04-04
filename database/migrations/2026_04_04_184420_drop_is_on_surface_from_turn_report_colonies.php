<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::statement('PRAGMA defer_foreign_keys = ON');

        try {
            $this->rebuild();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function rebuild(): void
    {
        DB::statement('DROP TABLE IF EXISTS turn_report_colonies_temp');
        DB::statement('
            CREATE TABLE turn_report_colonies_temp (
                id                INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                turn_report_id    INTEGER NOT NULL,
                source_colony_id  INTEGER,
                name              VARCHAR NOT NULL,
                kind              VARCHAR NOT NULL,
                tech_level        INTEGER NOT NULL,
                planet_id         INTEGER,
                orbit             INTEGER NOT NULL,
                star_x            INTEGER NOT NULL,
                star_y            INTEGER NOT NULL,
                star_z            INTEGER NOT NULL,
                star_sequence     INTEGER NOT NULL,
                rations           FLOAT NOT NULL,
                sol               FLOAT NOT NULL,
                birth_rate        FLOAT NOT NULL,
                death_rate        FLOAT NOT NULL,
                FOREIGN KEY (turn_report_id) REFERENCES turn_reports (id) ON DELETE CASCADE
            )
        ');

        DB::statement('
            INSERT INTO turn_report_colonies_temp
                (id, turn_report_id, source_colony_id, name, kind, tech_level,
                 planet_id, orbit, star_x, star_y, star_z, star_sequence,
                 rations, sol, birth_rate, death_rate)
            SELECT id, turn_report_id, source_colony_id, name, kind, tech_level,
                planet_id, orbit, star_x, star_y, star_z, star_sequence,
                rations, sol, birth_rate, death_rate
            FROM turn_report_colonies
        ');

        Schema::drop('turn_report_colonies');
        DB::statement('ALTER TABLE turn_report_colonies_temp RENAME TO turn_report_colonies');
    }

    public function down(): void
    {
        // Intentionally left empty — SQLite rebuild migrations are not reversible.
    }
};
