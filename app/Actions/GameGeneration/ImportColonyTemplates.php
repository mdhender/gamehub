<?php

namespace App\Actions\GameGeneration;

use App\Enums\InventorySection;
use App\Enums\PopulationClass;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class ImportColonyTemplates
{
    /** @var array<string, InventorySection> */
    private const SECTION_MAP = [
        'super-structure' => InventorySection::SuperStructure,
        'structure' => InventorySection::Structure,
        'operational' => InventorySection::Operational,
        'cargo' => InventorySection::Cargo,
    ];

    /**
     * Import colony templates from the parsed JSON data.
     *
     * @param  array<int, array<string, mixed>>  $templatesData
     */
    public function execute(Game $game, array $templatesData): void
    {
        DB::transaction(function () use ($game, $templatesData) {
            $game->colonyTemplates()->delete();

            foreach ($templatesData as $templateData) {
                $template = $game->colonyTemplates()->create([
                    'kind' => $templateData['kind'],
                    'tech_level' => $templateData['tech-level'],
                    'sol' => $templateData['sol'],
                    'birth_rate' => $templateData['birth-rate-pct'],
                    'death_rate' => $templateData['death-rate-pct'],
                ]);

                if (! empty($templateData['population'])) {
                    $this->createPopulation($template, $templateData['population']);
                }

                $this->createInventory($template, $templateData['inventory']);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $populationData
     */
    private function createPopulation(mixed $template, array $populationData): void
    {
        $baseRates = collect($populationData)
            ->whereIn('population_code', [
                PopulationClass::Unskilled->value,
                PopulationClass::Professional->value,
                PopulationClass::Soldier->value,
            ])
            ->pluck('pay_rate', 'population_code');

        foreach ($populationData as $popData) {
            $code = $popData['population_code'];

            if ($code === PopulationClass::ConstructionWorker->value) {
                $payRate = ($baseRates[PopulationClass::Professional->value] ?? 0)
                    + ($baseRates[PopulationClass::Unskilled->value] ?? 0);
            } elseif ($code === PopulationClass::Spy->value) {
                $payRate = ($baseRates[PopulationClass::Professional->value] ?? 0)
                    + ($baseRates[PopulationClass::Soldier->value] ?? 0);
            } else {
                $payRate = $popData['pay_rate'];
            }

            $template->population()->create([
                'population_code' => $code,
                'quantity' => $popData['quantity'],
                'pay_rate' => $payRate,
            ]);
        }
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $inventoryData
     */
    private function createInventory(mixed $template, array $inventoryData): void
    {
        foreach (self::SECTION_MAP as $jsonKey => $section) {
            foreach ($inventoryData[$jsonKey] ?? [] as $itemData) {
                $unit = $itemData['unit'];
                if (str_contains($unit, '-')) {
                    [$unitCode, $techLevel] = explode('-', $unit, 2);
                    $techLevel = (int) $techLevel;
                } else {
                    $unitCode = $unit;
                    $techLevel = 0;
                }

                $template->items()->create([
                    'unit' => $unitCode,
                    'tech_level' => $techLevel,
                    'quantity' => $itemData['quantity'],
                    'inventory_section' => $section,
                ]);
            }
        }
    }
}
