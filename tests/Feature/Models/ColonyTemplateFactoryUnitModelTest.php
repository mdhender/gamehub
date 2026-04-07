<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateFactoryGroup;
use App\Models\ColonyTemplateFactoryUnit;
use App\Models\Game;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplateFactoryUnitModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeUnit(array $attributes = []): ColonyTemplateFactoryUnit
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::query()->create([
            'game_id' => $game->id,
            'kind' => ColonyKind::Orbital,
            'tech_level' => 1,
        ]);
        $group = ColonyTemplateFactoryGroup::query()->create([
            'colony_template_id' => $template->id,
            'group_number' => 1,
            'orders_unit' => UnitCode::Factories,
            'orders_tech_level' => 1,
        ]);

        return ColonyTemplateFactoryUnit::query()->create(array_merge([
            'colony_template_factory_group_id' => $group->id,
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 5,
        ], $attributes));
    }

    #[Test]
    public function casts_unit_to_unit_code(): void
    {
        $unit = $this->makeUnit(['unit' => UnitCode::Factories]);

        $this->assertSame(UnitCode::Factories, $unit->fresh()->unit);
    }

    #[Test]
    public function stores_enum_backing_value(): void
    {
        $this->makeUnit(['unit' => UnitCode::Factories]);

        $this->assertDatabaseHas('colony_template_factory_units', ['unit' => 'FCT']);
    }

    #[Test]
    public function belongs_to_factory_group(): void
    {
        $unit = $this->makeUnit();

        $this->assertInstanceOf(ColonyTemplateFactoryGroup::class, $unit->colonyTemplateFactoryGroup);
    }
}
