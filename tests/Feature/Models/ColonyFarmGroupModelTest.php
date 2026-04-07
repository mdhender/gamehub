<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\Colony;
use App\Models\ColonyFarmGroup;
use App\Models\ColonyFarmUnit;
use App\Models\Empire;
use App\Models\Planet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyFarmGroupModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeColony(): Colony
    {
        $empire = Empire::factory()->create();
        $planet = Planet::factory()->create();

        return Colony::query()->create([
            'empire_id' => $empire->id,
            'star_id' => $planet->star_id,
            'planet_id' => $planet->id,
            'kind' => ColonyKind::Enclosed,
            'tech_level' => 1,
            'name' => 'Test Colony',
            'rations' => 1.0,
            'sol' => 0.0,
            'birth_rate' => 0.0,
            'death_rate' => 0.0,
        ]);
    }

    private function makeGroup(array $attributes = []): ColonyFarmGroup
    {
        $colony = $this->makeColony();

        return ColonyFarmGroup::query()->create(array_merge([
            'colony_id' => $colony->id,
            'group_number' => 1,
        ], $attributes));
    }

    #[Test]
    public function belongs_to_colony(): void
    {
        $group = $this->makeGroup();

        $this->assertInstanceOf(Colony::class, $group->colony);
    }

    #[Test]
    public function has_many_units(): void
    {
        $group = $this->makeGroup();

        ColonyFarmUnit::query()->create([
            'colony_farm_group_id' => $group->id,
            'unit' => UnitCode::Farms,
            'tech_level' => 1,
            'quantity' => 100,
            'stage' => 1,
        ]);

        $this->assertCount(1, $group->fresh()->units);
    }

    #[Test]
    public function colony_exposes_farm_groups(): void
    {
        $colony = $this->makeColony();

        $group = ColonyFarmGroup::query()->create([
            'colony_id' => $colony->id,
            'group_number' => 1,
        ]);

        $this->assertTrue($colony->farmGroups->contains($group));
    }
}
