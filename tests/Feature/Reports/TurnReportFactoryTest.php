<?php

namespace Tests\Feature\Reports;

use App\Enums\PopulationClass;
use App\Enums\UnitCode;
use App\Models\TurnReport;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyInventory;
use App\Models\TurnReportColonyPopulation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnReportFactoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function test_turn_report_factory_creates_persisted_model(): void
    {
        $report = TurnReport::factory()->create();

        $this->assertModelExists($report);
    }

    #[Test]
    public function test_turn_report_colony_factory_creates_persisted_model(): void
    {
        $colony = TurnReportColony::factory()->create();

        $this->assertModelExists($colony);
    }

    #[Test]
    public function test_turn_report_colony_inventory_factory_creates_with_enum_cast(): void
    {
        $inventory = TurnReportColonyInventory::factory()->create();

        $this->assertInstanceOf(UnitCode::class, $inventory->fresh()->unit_code);
    }

    #[Test]
    public function test_turn_report_colony_population_factory_creates_with_enum_cast(): void
    {
        $population = TurnReportColonyPopulation::factory()->create();

        $this->assertInstanceOf(PopulationClass::class, $population->fresh()->population_code);
    }
}
