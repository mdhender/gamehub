<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateMineGroup;
use App\Models\ColonyTemplateMineUnit;
use App\Models\Game;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplateMineUnitModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeUnit(array $attributes = []): ColonyTemplateMineUnit
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::query()->create([
            'game_id' => $game->id,
            'kind' => ColonyKind::Orbital,
            'tech_level' => 1,
        ]);
        $group = ColonyTemplateMineGroup::query()->create([
            'colony_template_id' => $template->id,
            'group_number' => 1,
        ]);

        return ColonyTemplateMineUnit::query()->create(array_merge([
            'colony_template_mine_group_id' => $group->id,
            'unit' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 100,
        ], $attributes));
    }

    #[Test]
    public function casts_unit_to_unit_code(): void
    {
        $unit = $this->makeUnit(['unit' => UnitCode::Mines]);

        $this->assertSame(UnitCode::Mines, $unit->fresh()->unit);
    }

    #[Test]
    public function stores_enum_backing_value(): void
    {
        $this->makeUnit(['unit' => UnitCode::Mines]);

        $this->assertDatabaseHas('colony_template_mine_units', ['unit' => 'MIN']);
    }

    #[Test]
    public function belongs_to_mine_group(): void
    {
        $unit = $this->makeUnit();

        $this->assertInstanceOf(ColonyTemplateMineGroup::class, $unit->colonyTemplateMineGroup);
    }
}
