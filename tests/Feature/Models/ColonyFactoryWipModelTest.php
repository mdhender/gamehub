<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\Colony;
use App\Models\ColonyFactoryGroup;
use App\Models\ColonyFactoryWip;
use App\Models\Empire;
use App\Models\Planet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyFactoryWipModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeWip(array $attributes = []): ColonyFactoryWip
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

        return ColonyFactoryWip::query()->create(array_merge([
            'colony_factory_group_id' => $group->id,
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

        $this->assertDatabaseHas('colony_factory_wip', ['quarter' => 1]);
    }

    #[Test]
    public function belongs_to_factory_group(): void
    {
        $wip = $this->makeWip();

        $this->assertInstanceOf(ColonyFactoryGroup::class, $wip->colonyFactoryGroup);
    }
}
