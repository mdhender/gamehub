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

class ColonyTemplateModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeTemplate(array $attributes = []): ColonyTemplate
    {
        $game = Game::factory()->create();

        return ColonyTemplate::query()->create(array_merge([
            'game_id' => $game->id,
            'kind' => ColonyKind::Orbital,
            'tech_level' => 1,
        ], $attributes));
    }

    #[Test]
    public function casts_kind_to_colony_kind(): void
    {
        $template = $this->makeTemplate(['kind' => ColonyKind::Orbital]);

        $this->assertSame(ColonyKind::Orbital, $template->fresh()->kind);
    }

    #[Test]
    public function stores_enum_backing_value(): void
    {
        $this->makeTemplate(['kind' => ColonyKind::Orbital]);

        $this->assertDatabaseHas('colony_templates', ['kind' => 'CORB']);
    }

    #[Test]
    public function existing_relationships_still_work(): void
    {
        $template = $this->makeTemplate();

        $this->assertInstanceOf(Game::class, $template->game);

        $item = ColonyTemplateItem::query()->create([
            'colony_template_id' => $template->id,
            'unit' => UnitCode::Factories->value,
            'tech_level' => 1,
            'quantity_assembled' => 0,
            'quantity_disassembled' => 0,
        ]);

        $this->assertTrue($template->items->contains($item));
    }

    #[Test]
    public function multiple_templates_per_game(): void
    {
        $game = Game::factory()->create();

        ColonyTemplate::query()->create(['game_id' => $game->id, 'kind' => ColonyKind::Enclosed, 'tech_level' => 1]);
        ColonyTemplate::query()->create(['game_id' => $game->id, 'kind' => ColonyKind::Orbital, 'tech_level' => 1]);

        $this->assertDatabaseCount('colony_templates', 2);
    }
}
