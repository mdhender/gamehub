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

class ColonyMineUnitModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeUnit(array $attributes = []): ColonyMineUnit
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
        $deposit = Deposit::factory()->create();
        $group = ColonyMineGroup::query()->create([
            'colony_id' => $colony->id,
            'group_number' => 1,
            'deposit_id' => $deposit->id,
        ]);

        return ColonyMineUnit::query()->create(array_merge([
            'colony_mine_group_id' => $group->id,
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

        $this->assertDatabaseHas('colony_mine_units', ['unit' => 'MIN']);
    }

    #[Test]
    public function belongs_to_mine_group(): void
    {
        $unit = $this->makeUnit();

        $this->assertInstanceOf(ColonyMineGroup::class, $unit->colonyMineGroup);
    }
}
