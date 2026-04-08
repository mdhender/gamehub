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

class ColonyTemplateMineGroupModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeGroup(array $attributes = []): ColonyTemplateMineGroup
    {
        $game = Game::factory()->create();
        $template = ColonyTemplate::query()->create([
            'game_id' => $game->id,
            'kind' => ColonyKind::Orbital,
            'tech_level' => 1,
        ]);

        return ColonyTemplateMineGroup::query()->create(array_merge([
            'colony_template_id' => $template->id,
            'group_number' => 1,
        ], $attributes));
    }

    #[Test]
    public function deposit_id_defaults_to_null(): void
    {
        $group = $this->makeGroup();

        $this->assertNull($group->fresh()->deposit_id);
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

        ColonyTemplateMineUnit::query()->create([
            'colony_template_mine_group_id' => $group->id,
            'unit' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 100,
        ]);

        $this->assertCount(1, $group->fresh()->units);
    }

    #[Test]
    public function colony_template_exposes_mine_groups(): void
    {
        $group = $this->makeGroup();

        $template = $group->colonyTemplate;
        $this->assertTrue($template->mineGroups->contains($group));
    }
}
