<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\InventorySection;
use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateFactoryGroup;
use App\Models\ColonyTemplateFarmGroup;
use App\Models\ColonyTemplateItem;
use App\Models\ColonyTemplateMineGroup;
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
            'quantity' => 0,
            'inventory_section' => InventorySection::Operational,
        ]);

        $this->assertTrue($template->items->contains($item));
    }

    #[Test]
    public function casts_metadata_fields(): void
    {
        $template = $this->makeTemplate([
            'sol' => 1.0,
            'birth_rate' => 0.0625,
            'death_rate' => 0.0625,
        ]);

        $fresh = $template->fresh();

        $this->assertSame(1.0, $fresh->sol);
        $this->assertSame(0.0625, $fresh->birth_rate);
        $this->assertSame(0.0625, $fresh->death_rate);
    }

    #[Test]
    public function metadata_fields_default_to_zero(): void
    {
        $template = $this->makeTemplate();

        $fresh = $template->fresh();

        $this->assertSame(0.0, $fresh->sol);
        $this->assertSame(0.0, $fresh->birth_rate);
        $this->assertSame(0.0, $fresh->death_rate);
    }

    #[Test]
    public function multiple_templates_per_game(): void
    {
        $game = Game::factory()->create();

        ColonyTemplate::query()->create(['game_id' => $game->id, 'kind' => ColonyKind::Enclosed, 'tech_level' => 1]);
        ColonyTemplate::query()->create(['game_id' => $game->id, 'kind' => ColonyKind::Orbital, 'tech_level' => 1]);

        $this->assertDatabaseCount('colony_templates', 2);
    }

    #[Test]
    public function has_many_factory_groups(): void
    {
        $template = $this->makeTemplate();

        $group = ColonyTemplateFactoryGroup::query()->create([
            'colony_template_id' => $template->id,
            'group_number' => 1,
            'orders_unit' => UnitCode::Factories,
            'orders_tech_level' => 1,
        ]);

        $this->assertTrue($template->factoryGroups->contains($group));
    }

    #[Test]
    public function has_many_farm_groups(): void
    {
        $template = $this->makeTemplate();

        $group = ColonyTemplateFarmGroup::query()->create([
            'colony_template_id' => $template->id,
            'group_number' => 1,
        ]);

        $this->assertTrue($template->farmGroups->contains($group));
    }

    #[Test]
    public function has_many_mine_groups(): void
    {
        $template = $this->makeTemplate();

        $group = ColonyTemplateMineGroup::query()->create([
            'colony_template_id' => $template->id,
            'group_number' => 1,
        ]);

        $this->assertTrue($template->mineGroups->contains($group));
    }
}
