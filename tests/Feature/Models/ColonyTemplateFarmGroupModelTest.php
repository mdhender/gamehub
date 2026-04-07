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

class ColonyTemplateFarmGroupModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeGroup(array $attributes = []): ColonyTemplateFarmGroup
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::query()->create([
            'game_id' => $game->id,
            'kind' => ColonyKind::Orbital,
            'tech_level' => 1,
        ]);

        return ColonyTemplateFarmGroup::query()->create(array_merge([
            'colony_template_id' => $template->id,
            'group_number' => 1,
        ], $attributes));
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

        ColonyTemplateFarmUnit::query()->create([
            'colony_template_farm_group_id' => $group->id,
            'unit' => UnitCode::Farms,
            'tech_level' => 1,
            'quantity' => 100,
            'stage' => 1,
        ]);

        $this->assertCount(1, $group->fresh()->units);
    }

    #[Test]
    public function colony_template_exposes_farm_groups(): void
    {
        $group = $this->makeGroup();

        $template = $group->colonyTemplate;
        $this->assertTrue($template->farmGroups->contains($group));
    }
}
