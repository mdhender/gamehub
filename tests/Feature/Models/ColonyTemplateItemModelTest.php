<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateItem;
use App\Models\Game;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplateItemModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeItem(array $attributes = []): ColonyTemplateItem
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::query()->create([
            'game_id' => $game->id,
            'kind' => ColonyKind::Orbital,
            'tech_level' => 1,
        ]);

        return ColonyTemplateItem::query()->create(array_merge([
            'colony_template_id' => $template->id,
            'unit' => UnitCode::Food,
            'tech_level' => 1,
            'quantity_assembled' => 0,
            'quantity_disassembled' => 0,
        ], $attributes));
    }

    #[Test]
    public function casts_unit_to_unit_code(): void
    {
        $item = $this->makeItem(['unit' => UnitCode::Food]);

        $this->assertSame(UnitCode::Food, $item->fresh()->unit);
    }

    #[Test]
    public function stores_enum_backing_value(): void
    {
        $this->makeItem(['unit' => UnitCode::Food]);

        $this->assertDatabaseHas('colony_template_items', ['unit' => 'FOOD']);
    }

    #[Test]
    public function relationship_to_colony_template_still_works(): void
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::query()->create([
            'game_id' => $game->id,
            'kind' => ColonyKind::Enclosed,
            'tech_level' => 1,
        ]);

        $item = ColonyTemplateItem::query()->create([
            'colony_template_id' => $template->id,
            'unit' => UnitCode::Food,
            'tech_level' => 1,
            'quantity_assembled' => 0,
            'quantity_disassembled' => 0,
        ]);

        $this->assertTrue($item->colonyTemplate->is($template));
    }
}
