<?php

namespace Database\Factories;

use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateItem;
use App\Models\Game;
use App\Models\HomeSystemTemplate;
use App\Models\HomeSystemTemplateDeposit;
use App\Models\HomeSystemTemplatePlanet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'is_active' => true,
            'prng_seed' => fake()->sha256(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withDefaultTemplates(): static
    {
        return $this->afterCreating(function (Game $game): void {
            $homeSystemData = json_decode(
                file_get_contents(base_path('sample-data/beta/home-system-template.json')),
                true
            );

            $template = HomeSystemTemplate::create(['game_id' => $game->id]);

            foreach ($homeSystemData['planets'] as $planetData) {
                $planet = HomeSystemTemplatePlanet::create([
                    'home_system_template_id' => $template->id,
                    'orbit' => $planetData['orbit'],
                    'type' => $planetData['type'],
                    'habitability' => $planetData['habitability'],
                    'is_homeworld' => $planetData['homeworld'] ?? false,
                ]);

                foreach ($planetData['deposits'] ?? [] as $depositData) {
                    HomeSystemTemplateDeposit::create([
                        'home_system_template_planet_id' => $planet->id,
                        'resource' => $depositData['resource'],
                        'yield_pct' => $depositData['yield_pct'],
                        'quantity_remaining' => $depositData['quantity_remaining'],
                    ]);
                }
            }

            $colonyDataArray = json_decode(
                file_get_contents(base_path('sample-data/beta/colony-template.json')),
                true
            );

            foreach ($colonyDataArray as $colonyData) {
                $colonyTemplate = ColonyTemplate::create([
                    'game_id' => $game->id,
                    'kind' => $colonyData['kind'],
                    'tech_level' => $colonyData['tech-level'],
                ]);

                $allItems = array_merge(
                    $colonyData['inventory']['operational'] ?? [],
                    $colonyData['inventory']['stored'] ?? [],
                );

                foreach ($allItems as $itemData) {
                    $parts = explode('-', $itemData['unit'], 2);
                    $unit = $parts[0];
                    $techLevel = isset($parts[1]) ? (int) $parts[1] : 0;

                    ColonyTemplateItem::create([
                        'colony_template_id' => $colonyTemplate->id,
                        'unit' => $unit,
                        'tech_level' => $techLevel,
                        'quantity_assembled' => $itemData['quantity'],
                        'quantity_disassembled' => 0,
                    ]);
                }
            }
        });
    }
}
