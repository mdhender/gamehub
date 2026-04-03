<?php

namespace Tests\Feature\Models;

use App\Enums\PopulationClass;
use App\Models\Colony;
use App\Models\ColonyPopulation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyPopulationModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function test_it_uses_the_colony_population_table(): void
    {
        $colony = Colony::factory()->create();

        ColonyPopulation::create([
            'colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        $this->assertDatabaseHas('colony_population', ['colony_id' => $colony->id]);
    }

    #[Test]
    public function test_it_casts_population_code_to_population_class_enum(): void
    {
        $colony = Colony::factory()->create();

        $record = ColonyPopulation::create([
            'colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        $this->assertSame(PopulationClass::Unskilled, $record->fresh()->population_code);
    }

    #[Test]
    public function test_it_stores_enum_backing_value_in_database(): void
    {
        $colony = Colony::factory()->create();

        ColonyPopulation::create([
            'colony_id' => $colony->id,
            'population_code' => PopulationClass::Soldier,
            'quantity' => 50,
            'pay_rate' => 2.0,
        ]);

        $this->assertDatabaseHas('colony_population', ['population_code' => 'SLD']);
    }

    #[Test]
    public function test_it_casts_pay_rate_to_float(): void
    {
        $colony = Colony::factory()->create();

        $record = ColonyPopulation::create([
            'colony_id' => $colony->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 10,
            'pay_rate' => 0.125,
        ]);

        $fresh = $record->fresh();

        $this->assertTrue(is_float($fresh->pay_rate));
        $this->assertEquals(0.125, $fresh->pay_rate);
    }

    #[Test]
    public function test_it_belongs_to_a_colony(): void
    {
        $colony = Colony::factory()->create();

        $record = ColonyPopulation::create([
            'colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        $this->assertInstanceOf(Colony::class, $record->colony);
    }

    #[Test]
    public function test_colony_has_population_relationship(): void
    {
        $colony = Colony::factory()->create();

        ColonyPopulation::create([
            'colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        ColonyPopulation::create([
            'colony_id' => $colony->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 50,
            'pay_rate' => 2.0,
        ]);

        $this->assertCount(2, $colony->population);
    }

    #[Test]
    public function test_it_defaults_rebel_quantity_to_zero(): void
    {
        $colony = Colony::factory()->create();

        ColonyPopulation::create([
            'colony_id' => $colony->id,
            'population_code' => PopulationClass::Soldier,
            'quantity' => 20,
            'pay_rate' => 1.5,
        ]);

        $this->assertDatabaseHas('colony_population', ['rebel_quantity' => 0]);
    }
}
