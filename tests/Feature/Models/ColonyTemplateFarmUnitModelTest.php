<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateFarmGroup;
use App\Models\ColonyTemplateFarmUnit;
use App\Models\Game;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplateFarmUnitModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeUnit(array $attributes = []): ColonyTemplateFarmUnit
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::query()->create([
            'game_id' => $game->id,
            'kind' => ColonyKind::Orbital,
            'tech_level' => 1,
        ]);
        $group = ColonyTemplateFarmGroup::query()->create([
            'colony_template_id' => $template->id,
            'group_number' => 1,
        ]);

        return ColonyTemplateFarmUnit::query()->create(array_merge([
            'colony_template_farm_group_id' => $group->id,
            'unit' => UnitCode::Farms,
            'tech_level' => 1,
            'quantity' => 100,
            'stage' => 1,
        ], $attributes));
    }

    #[Test]
    public function casts_unit_to_unit_code(): void
    {
        $unit = $this->makeUnit(['unit' => UnitCode::Farms]);

        $this->assertSame(UnitCode::Farms, $unit->fresh()->unit);
    }

    #[Test]
    public function stores_enum_backing_value(): void
    {
        $this->makeUnit(['unit' => UnitCode::Farms]);

        $this->assertDatabaseHas('colony_template_farm_units', ['unit' => 'FRM']);
    }

    #[Test]
    public function belongs_to_farm_group(): void
    {
        $unit = $this->makeUnit();

        $this->assertInstanceOf(ColonyTemplateFarmGroup::class, $unit->colonyTemplateFarmGroup);
    }
}
