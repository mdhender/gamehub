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

                $production = $templateData['production'] ?? [];

                if (! empty($production['factories'])) {
                    $this->createFactoryGroups($template, $production['factories']);
                }

                if (! empty($production['farms'])) {
                    $this->createFarmGroups($template, $production['farms']);
                }

                if (! empty($production['mines'])) {
                    $this->createMineGroups($template, $production['mines']);
                }
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
                [$unitCode, $techLevel] = self::parseUnitString($itemData['unit']);

                $template->items()->create([
                    'unit' => $unitCode,
                    'tech_level' => $techLevel,
                    'quantity' => $itemData['quantity'],
                    'inventory_section' => $section,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $factoriesData
     */
    private function createFactoryGroups(mixed $template, array $factoriesData): void
    {
        foreach ($factoriesData as $groupData) {
            [$ordersUnit, $ordersTechLevel] = self::parseUnitString($groupData['orders']);

            $group = $template->factoryGroups()->create([
                'group_number' => $groupData['group'],
                'orders_unit' => $ordersUnit,
                'orders_tech_level' => $ordersTechLevel,
                'pending_orders_unit' => null,
                'pending_orders_tech_level' => null,
            ]);

            foreach ($groupData['units'] as $unitData) {
                [$unitCode, $techLevel] = self::parseUnitString($unitData['unit']);

                $group->units()->create([
                    'unit' => $unitCode,
                    'tech_level' => $techLevel,
                    'quantity' => $unitData['quantity'],
                ]);
            }

            $quarterMap = ['q1' => 1, 'q2' => 2, 'q3' => 3];

            foreach ($quarterMap as $key => $quarter) {
                $wipData = $groupData['work-in-progress'][$key];
                [$wipUnit, $wipTechLevel] = self::parseUnitString($wipData['unit']);

                $group->wip()->create([
                    'quarter' => $quarter,
                    'unit' => $wipUnit,
                    'tech_level' => $wipTechLevel,
                    'quantity' => $wipData['quantity'],
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $farmsData
     */
    private function createFarmGroups(mixed $template, array $farmsData): void
    {
        foreach ($farmsData as $groupData) {
            $group = $template->farmGroups()->create([
                'group_number' => $groupData['group'],
            ]);

            $presentStages = [];
            $firstUnit = null;
            $firstTechLevel = null;

            foreach ($groupData['units'] as $unitData) {
                [$unitCode, $techLevel] = self::parseUnitString($unitData['unit']);

                $firstUnit ??= $unitCode;
                $firstTechLevel ??= $techLevel;

                $stage = $unitData['stage'];
                $presentStages[] = $stage;

                $group->units()->create([
                    'unit' => $unitCode,
                    'tech_level' => $techLevel,
                    'quantity' => $unitData['quantity'] ?? 0,
                    'stage' => $stage,
                ]);
            }

            $missingStages = array_diff([1, 2, 3, 4], $presentStages);

            foreach ($missingStages as $stage) {
                $group->units()->create([
                    'unit' => $firstUnit,
                    'tech_level' => $firstTechLevel,
                    'quantity' => 0,
                    'stage' => $stage,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $minesData
     */
    private function createMineGroups(mixed $template, array $minesData): void
    {
        foreach ($minesData as $groupData) {
            $group = $template->mineGroups()->create([
                'group_number' => $groupData['group'],
                'deposit_id' => null,
            ]);

            foreach ($groupData['units'] as $unitData) {
                [$unitCode, $techLevel] = self::parseUnitString($unitData['unit']);

                $group->units()->create([
                    'unit' => $unitCode,
                    'tech_level' => $techLevel,
                    'quantity' => $unitData['quantity'],
                ]);
            }
        }
    }

    /**
     * Parse a unit string like "FCT-1" into ['FCT', 1] or "CNGD" into ['CNGD', 0].
     *
     * @return array{0: string, 1: int}
     */
    private static function parseUnitString(string $unit): array
    {
        if (str_contains($unit, '-')) {
            [$code, $level] = explode('-', $unit, 2);

            return [$code, (int) $level];
        }

        return [$unit, 0];
    }
}
