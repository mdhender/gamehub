<?php

namespace Tests\Feature\Reports;

use App\Enums\DepositResource;
use App\Enums\PlanetType;
use App\Enums\PopulationClass;
use App\Enums\UnitCode;
use App\Models\TurnReport;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyInventory;
use App\Models\TurnReportColonyPopulation;
use App\Models\TurnReportSurvey;
use App\Models\TurnReportSurveyDeposit;
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

    #[Test]
    public function test_turn_report_survey_factory_creates_with_planet_type_enum(): void
    {
        $survey = TurnReportSurvey::factory()->create();

        $this->assertInstanceOf(PlanetType::class, $survey->fresh()->planet_type);
    }

    #[Test]
    public function test_turn_report_survey_deposit_factory_creates_with_deposit_resource_enum(): void
    {
        $deposit = TurnReportSurveyDeposit::factory()->create();

        $this->assertInstanceOf(DepositResource::class, $deposit->fresh()->resource);
    }
}
