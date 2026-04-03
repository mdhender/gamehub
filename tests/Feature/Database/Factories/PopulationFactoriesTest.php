<?php

namespace Tests\Feature\Database\Factories;

use App\Enums\PopulationClass;
use App\Models\Colony;
use App\Models\ColonyPopulation;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplatePopulation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PopulationFactoriesTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ColonyPopulationFactory tests

    #[Test]
    public function test_colony_population_factory_creates_a_valid_record(): void
    {
        $record = ColonyPopulation::factory()->create();

        $this->assertInstanceOf(ColonyPopulation::class, $record);
    }

    #[Test]
    public function test_colony_population_factory_defaults_rebel_quantity_to_zero(): void
    {
        $record = ColonyPopulation::factory()->create();

        $this->assertSame(0, $record->rebel_quantity);
    }

    #[Test]
    public function test_colony_population_factory_creates_related_colony(): void
    {
        $record = ColonyPopulation::factory()->create();

        $this->assertInstanceOf(Colony::class, $record->colony);
    }

    #[Test]
    public function test_colony_population_factory_uses_population_class_enum(): void
    {
        $record = ColonyPopulation::factory()->create();

        $this->assertInstanceOf(PopulationClass::class, $record->fresh()->population_code);
    }

    #[Test]
    public function test_colony_population_factory_accepts_explicit_population_code(): void
    {
        $record = ColonyPopulation::factory()->create([
            'population_code' => PopulationClass::Soldier,
        ]);

        $this->assertSame(PopulationClass::Soldier, $record->population_code);
    }

    // ColonyTemplatePopulationFactory tests

    #[Test]
    public function test_colony_template_population_factory_creates_a_valid_record(): void
    {
        $record = ColonyTemplatePopulation::factory()->create();

        $this->assertInstanceOf(ColonyTemplatePopulation::class, $record);
    }

    #[Test]
    public function test_colony_template_population_factory_creates_related_template(): void
    {
        $record = ColonyTemplatePopulation::factory()->create();

        $this->assertInstanceOf(ColonyTemplate::class, $record->colonyTemplate);
    }

    #[Test]
    public function test_colony_template_population_factory_uses_population_class_enum(): void
    {
        $record = ColonyTemplatePopulation::factory()->create();

        $this->assertInstanceOf(PopulationClass::class, $record->fresh()->population_code);
    }

    #[Test]
    public function test_colony_template_population_factory_accepts_explicit_population_code(): void
    {
        $record = ColonyTemplatePopulation::factory()->create([
            'population_code' => PopulationClass::Professional,
        ]);

        $this->assertSame(PopulationClass::Professional, $record->population_code);
    }
}
