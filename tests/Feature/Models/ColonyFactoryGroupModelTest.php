<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\Colony;
use App\Models\ColonyFactoryGroup;
use App\Models\ColonyFactoryUnit;
use App\Models\ColonyFactoryWip;
use App\Models\Empire;
use App\Models\Planet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyFactoryGroupModelTest extends TestCase
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

    private function makeGroup(array $attributes = []): ColonyFactoryGroup
    {
        $colony = $this->makeColony();

        return ColonyFactoryGroup::query()->create(array_merge([
            'colony_id' => $colony->id,
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
    public function casts_remainder_fields_to_float(): void
    {
        $group = $this->makeGroup([
            'input_remainder_mets' => 1.5,
            'input_remainder_nmts' => 2.3,
        ]);

        $fresh = $group->fresh();
        $this->assertIsFloat($fresh->input_remainder_mets);
        $this->assertIsFloat($fresh->input_remainder_nmts);
        $this->assertSame(1.5, $fresh->input_remainder_mets);
        $this->assertSame(2.3, $fresh->input_remainder_nmts);
    }

    #[Test]
    public function remainder_fields_default_to_zero(): void
    {
        $group = $this->makeGroup();

        $fresh = $group->fresh();
        $this->assertSame(0.0, $fresh->input_remainder_mets);
        $this->assertSame(0.0, $fresh->input_remainder_nmts);
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

        ColonyFactoryUnit::query()->create([
            'colony_factory_group_id' => $group->id,
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

        ColonyFactoryWip::query()->create([
            'colony_factory_group_id' => $group->id,
            'quarter' => 1,
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 3,
        ]);

        $this->assertCount(1, $group->fresh()->wip);
    }

    #[Test]
    public function colony_exposes_factory_groups(): void
    {
        $colony = $this->makeColony();

        $group = ColonyFactoryGroup::query()->create([
            'colony_id' => $colony->id,
            'group_number' => 1,
            'orders_unit' => UnitCode::Factories,
            'orders_tech_level' => 1,
        ]);

        $this->assertTrue($colony->factoryGroups->contains($group));
    }
}
