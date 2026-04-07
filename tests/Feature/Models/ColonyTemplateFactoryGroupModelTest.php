<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateFactoryGroup;
use App\Models\ColonyTemplateFactoryUnit;
use App\Models\ColonyTemplateFactoryWip;
use App\Models\Game;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplateFactoryGroupModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeGroup(array $attributes = []): ColonyTemplateFactoryGroup
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::query()->create([
            'game_id' => $game->id,
            'kind' => ColonyKind::Orbital,
            'tech_level' => 1,
        ]);

        return ColonyTemplateFactoryGroup::query()->create(array_merge([
            'colony_template_id' => $template->id,
            'group_number' => 1,
            'orders_unit' => UnitCode::Factories,
            'orders_tech_level' => 1,
        ], $attributes));
    }

    #[Test]
    public function casts_orders_unit_to_unit_code(): void
    {
        $group = $this->makeGroup(['orders_unit' => UnitCode::Factories]);

        $this->assertSame(UnitCode::Factories, $group->fresh()->orders_unit);
    }

    #[Test]
    public function casts_pending_orders_unit_to_unit_code(): void
    {
        $group = $this->makeGroup(['pending_orders_unit' => UnitCode::Mines]);

        $this->assertSame(UnitCode::Mines, $group->fresh()->pending_orders_unit);
    }

    #[Test]
    public function pending_orders_default_to_null(): void
    {
        $group = $this->makeGroup();

        $fresh = $group->fresh();
        $this->assertNull($fresh->pending_orders_unit);
        $this->assertNull($fresh->pending_orders_tech_level);
    }

    #[Test]
    public function belongs_to_colony_template(): void
    {
        $group = $this->makeGroup();

        $this->assertInstanceOf(ColonyTemplate::class, $group->colonyTemplate);
    }

    #[Test]
    public function has_many_units(): void
    {
        $group = $this->makeGroup();

        ColonyTemplateFactoryUnit::query()->create([
            'colony_template_factory_group_id' => $group->id,
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 5,
        ]);

        $this->assertCount(1, $group->fresh()->units);
    }

    #[Test]
    public function has_many_wip(): void
    {
        $group = $this->makeGroup();

        ColonyTemplateFactoryWip::query()->create([
            'colony_template_factory_group_id' => $group->id,
            'quarter' => 1,
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 3,
        ]);

        $this->assertCount(1, $group->fresh()->wip);
    }

    #[Test]
    public function colony_template_exposes_factory_groups(): void
    {
        $group = $this->makeGroup();

        $template = $group->colonyTemplate;
        $this->assertTrue($template->factoryGroups->contains($group));
    }
}
