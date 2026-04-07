<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\Colony;
use App\Models\ColonyFactoryGroup;
use App\Models\ColonyFactoryUnit;
use App\Models\Empire;
use App\Models\Planet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyFactoryUnitModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeUnit(array $attributes = []): ColonyFactoryUnit
    {
        $empire = Empire::factory()->create();
        $planet = Planet::factory()->create();
        $colony = Colony::query()->create([
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
        $group = ColonyFactoryGroup::query()->create([
            'colony_id' => $colony->id,
            'group_number' => 1,
            'orders_unit' => UnitCode::Factories,
            'orders_tech_level' => 1,
        ]);

        return ColonyFactoryUnit::query()->create(array_merge([
            'colony_factory_group_id' => $group->id,
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

        $this->assertDatabaseHas('colony_factory_units', ['unit' => 'FCT']);
    }

    #[Test]
    public function belongs_to_factory_group(): void
    {
        $unit = $this->makeUnit();

        $this->assertInstanceOf(ColonyFactoryGroup::class, $unit->colonyFactoryGroup);
    }
}
