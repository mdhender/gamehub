<?php

namespace Tests\Feature\Models;

use App\Enums\PopulationClass;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplatePopulation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ColonyTemplatePopulationModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function test_it_uses_the_colony_template_population_table(): void
    {
        $template = ColonyTemplate::factory()->create();

        ColonyTemplatePopulation::create([
            'colony_template_id' => $template->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        $this->assertDatabaseHas('colony_template_population', ['colony_template_id' => $template->id]);
    }

    #[Test]
    public function test_it_casts_population_code_to_population_class_enum(): void
    {
        $template = ColonyTemplate::factory()->create();

        $record = ColonyTemplatePopulation::create([
            'colony_template_id' => $template->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        $this->assertSame(PopulationClass::Professional, $record->fresh()->population_code);
    }

    #[Test]
    public function test_it_stores_enum_backing_value_in_database(): void
    {
        $template = ColonyTemplate::factory()->create();

        ColonyTemplatePopulation::create([
            'colony_template_id' => $template->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 50,
            'pay_rate' => 2.0,
        ]);

        $this->assertDatabaseHas('colony_template_population', ['population_code' => 'PRO']);
    }

    #[Test]
    public function test_it_casts_pay_rate_to_float(): void
    {
        $template = ColonyTemplate::factory()->create();

        $record = ColonyTemplatePopulation::create([
            'colony_template_id' => $template->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 10,
            'pay_rate' => 0.375,
        ]);

        $fresh = $record->fresh();

        $this->assertTrue(is_float($fresh->pay_rate));
        $this->assertEquals(0.375, $fresh->pay_rate);
    }

    #[Test]
    public function test_it_belongs_to_a_colony_template(): void
    {
        $template = ColonyTemplate::factory()->create();

        $record = ColonyTemplatePopulation::create([
            'colony_template_id' => $template->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        $this->assertInstanceOf(ColonyTemplate::class, $record->colonyTemplate);
    }

    #[Test]
    public function test_colony_template_has_population_relationship(): void
    {
        $template = ColonyTemplate::factory()->create();

        ColonyTemplatePopulation::create([
            'colony_template_id' => $template->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'pay_rate' => 1.0,
        ]);

        ColonyTemplatePopulation::create([
            'colony_template_id' => $template->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 50,
            'pay_rate' => 2.0,
        ]);

        $this->assertCount(2, $template->population);
    }
}
