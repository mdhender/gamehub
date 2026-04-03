<?php

namespace Tests\Feature\Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyInventory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyInventoryFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function factory_creates_a_valid_row(): void
    {
        $inventory = ColonyInventory::factory()->create();

        $this->assertModelExists($inventory);
    }

    #[Test]
    public function default_unit_resolves_as_unit_code(): void
    {
        $inventory = ColonyInventory::factory()->create();

        $this->assertInstanceOf(UnitCode::class, $inventory->fresh()->unit);
    }

    #[Test]
    public function raw_db_value_is_a_valid_enum_backing_value(): void
    {
        $inventory = ColonyInventory::factory()->create();

        $validValues = array_map(fn (UnitCode $code) => $code->value, UnitCode::cases());

        $this->assertContains($inventory->fresh()->unit->value, $validValues);
    }

    #[Test]
    public function explicit_override_works(): void
    {
        $inventory = ColonyInventory::factory()->create(['unit' => UnitCode::Fuel]);

        $this->assertSame(UnitCode::Fuel, $inventory->fresh()->unit);
    }
}
