<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\Colony;
use App\Models\ColonyMineGroup;
use App\Models\ColonyMineUnit;
use App\Models\Deposit;
use App\Models\Empire;
use App\Models\Planet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyMineGroupModelTest extends TestCase
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

    private function makeGroup(array $attributes = []): ColonyMineGroup
    {
        $colony = $this->makeColony();
        $deposit = Deposit::factory()->create();

        return ColonyMineGroup::query()->create(array_merge([
            'colony_id' => $colony->id,
            'group_number' => 1,
            'deposit_id' => $deposit->id,
        ], $attributes));
    }

    #[Test]
    public function belongs_to_colony(): void
    {
        $group = $this->makeGroup();

        $this->assertInstanceOf(Colony::class, $group->colony);
    }

    #[Test]
    public function belongs_to_deposit(): void
    {
        $group = $this->makeGroup();

        $this->assertInstanceOf(Deposit::class, $group->deposit);
    }

    #[Test]
    public function has_many_units(): void
    {
        $group = $this->makeGroup();

        ColonyMineUnit::query()->create([
            'colony_mine_group_id' => $group->id,
            'unit' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 100,
        ]);

        $this->assertCount(1, $group->fresh()->units);
    }

    #[Test]
    public function colony_exposes_mine_groups(): void
    {
        $colony = $this->makeColony();
        $deposit = Deposit::factory()->create();

        $group = ColonyMineGroup::query()->create([
            'colony_id' => $colony->id,
            'group_number' => 1,
            'deposit_id' => $deposit->id,
        ]);

        $this->assertTrue($colony->mineGroups->contains($group));
    }
}
