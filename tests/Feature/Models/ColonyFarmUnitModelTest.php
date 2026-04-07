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

class ColonyFarmUnitModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeUnit(array $attributes = []): ColonyFarmUnit
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
        $group = ColonyFarmGroup::query()->create([
            'colony_id' => $colony->id,
            'group_number' => 1,
        ]);

        return ColonyFarmUnit::query()->create(array_merge([
            'colony_farm_group_id' => $group->id,
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

        $this->assertDatabaseHas('colony_farm_units', ['unit' => 'FRM']);
    }

    #[Test]
    public function belongs_to_farm_group(): void
    {
        $unit = $this->makeUnit();

        $this->assertInstanceOf(ColonyFarmGroup::class, $unit->colonyFarmGroup);
    }
}
