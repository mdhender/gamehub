<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateFactoryGroup;
use App\Models\ColonyTemplateFactoryWip;
use App\Models\Game;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplateFactoryWipModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeWip(array $attributes = []): ColonyTemplateFactoryWip
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

        return ColonyTemplateFactoryWip::query()->create(array_merge([
            'colony_template_factory_group_id' => $group->id,
            'quarter' => 1,
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 3,
        ], $attributes));
    }

    #[Test]
    public function casts_unit_to_unit_code(): void
    {
        $wip = $this->makeWip(['unit' => UnitCode::Factories]);

        $this->assertSame(UnitCode::Factories, $wip->fresh()->unit);
    }

    #[Test]
    public function uses_correct_table_name(): void
    {
        $this->makeWip();

        $this->assertDatabaseHas('colony_template_factory_wip', ['quarter' => 1]);
    }

    #[Test]
    public function belongs_to_factory_group(): void
    {
        $wip = $this->makeWip();

        $this->assertInstanceOf(ColonyTemplateFactoryGroup::class, $wip->colonyTemplateFactoryGroup);
    }
}
