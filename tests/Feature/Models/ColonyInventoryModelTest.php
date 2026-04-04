<?php

namespace Tests\Feature\Models;

use App\Enums\ColonyKind;
use App\Enums\UnitCode;
use App\Models\Colony;
use App\Models\ColonyInventory;
use App\Models\Empire;
use App\Models\Planet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyInventoryModelTest extends TestCase
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
        ]);
    }

    private function makeInventory(Colony $colony, array $attributes = []): ColonyInventory
    {
        return ColonyInventory::query()->create(array_merge([
            'colony_id' => $colony->id,
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity_assembled' => 0,
            'quantity_disassembled' => 0,
        ], $attributes));
    }

    #[Test]
    public function casts_unit_to_unit_code(): void
    {
        $colony = $this->makeColony();
        $inventory = $this->makeInventory($colony, ['unit' => UnitCode::Factories]);

        $this->assertSame(UnitCode::Factories, $inventory->fresh()->unit);
    }

    #[Test]
    public function stores_enum_backing_value_in_database(): void
    {
        $colony = $this->makeColony();
        $this->makeInventory($colony, ['unit' => UnitCode::Factories]);

        $this->assertDatabaseHas('colony_inventory', ['unit' => 'FCT']);
    }

    #[Test]
    public function relationship_to_colony_still_works(): void
    {
        $colony = $this->makeColony();
        $inventory = $this->makeInventory($colony);

        $this->assertTrue($inventory->colony->is($colony));
    }
}
