<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MissingIndexesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function getIndexedColumns(string $table): array
    {
        $indexes = DB::select("PRAGMA index_list({$table})");
        $columns = [];

        foreach ($indexes as $index) {
            $info = DB::select("PRAGMA index_info({$index->name})");
            foreach ($info as $col) {
                $columns[] = $col->name;
            }
        }

        return $columns;
    }

    public function test_colonies_has_indexes_on_foreign_key_columns(): void
    {
        $columns = $this->getIndexedColumns('colonies');

        $this->assertContains('empire_id', $columns);
        $this->assertContains('star_id', $columns);
        $this->assertContains('planet_id', $columns);
    }

    public function test_colony_inventory_has_index_on_colony_id(): void
    {
        $columns = $this->getIndexedColumns('colony_inventory');

        $this->assertContains('colony_id', $columns);
    }

    public function test_colony_template_items_has_index_on_colony_template_id(): void
    {
        $columns = $this->getIndexedColumns('colony_template_items');

        $this->assertContains('colony_template_id', $columns);
    }

    public function test_colony_templates_has_index_on_game_id(): void
    {
        $columns = $this->getIndexedColumns('colony_templates');

        $this->assertContains('game_id', $columns);
    }

    public function test_games_has_index_on_status(): void
    {
        $columns = $this->getIndexedColumns('games');

        $this->assertContains('status', $columns);
    }
}
