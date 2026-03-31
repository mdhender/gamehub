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

            $colonyData = json_decode(
                file_get_contents(base_path('sample-data/beta/colony-template.json')),
                true
            );

            $normalized = array_change_key_case($colonyData, CASE_LOWER);

            $colonyTemplate = ColonyTemplate::create([
                'game_id' => $game->id,
                'kind' => $normalized['kind'],
                'tech_level' => $normalized['techlevel'],
            ]);

            foreach ($normalized['inventory'] as $itemData) {
                $item = array_change_key_case($itemData, CASE_LOWER);
                ColonyTemplateItem::create([
                    'colony_template_id' => $colonyTemplate->id,
                    'unit' => $item['unit'],
                    'tech_level' => $item['techlevel'],
                    'quantity_assembled' => $item['quantityassembled'],
                    'quantity_disassembled' => $item['quantitydisassembled'],
                ]);
            }
        });
    }
}
