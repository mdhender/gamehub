<?php

namespace App\Support\TurnReports;

use App\Enums\PopulationClass;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Turn;
use App\Models\TurnReport;

class TurnReportJsonExporter
{
    /**
     * Build the JSON-serializable payload for a turn report download.
     *
     * @return array<string, mixed>
     */
    public function toArray(Game $game, Turn $turn, Empire $empire, TurnReport $report): array
    {
        return [
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
            ],
            'turn' => [
                'id' => $turn->id,
                'number' => $turn->number,
                'status' => $turn->status->value,
                'reports_locked_at' => $turn->reports_locked_at?->toIso8601String(),
            ],
            'empire' => [
                'id' => $empire->id,
                'name' => $empire->name,
            ],
            'generated_at' => $report->generated_at->toIso8601String(),
            'colonies' => $report->colonies->map(fn ($colony) => [
                'id' => $colony->id,
                'source_colony_id' => $colony->source_colony_id,
                'name' => $colony->name,
                'kind' => $colony->kind->value,
                'tech_level' => $colony->tech_level,
                'planet_id' => $colony->planet_id,
                'orbit' => $colony->orbit,
                'star_x' => $colony->star_x,
                'star_y' => $colony->star_y,
                'star_z' => $colony->star_z,
                'star_sequence' => $colony->star_sequence,
                'rations' => $colony->rations,
                'sol' => $colony->sol,
                'birth_rate' => $colony->birth_rate,
                'death_rate' => $colony->death_rate,
                'inventory' => $colony->inventory->map(fn ($item) => [
                    'unit_code' => $item->unit_code->value,
                    'tech_level' => $item->tech_level,
                    'inventory_section' => $item->inventory_section->value,
                    'quantity' => $item->quantity,
                ])->values(),
                'factory_groups' => $colony->factoryGroups->map(fn ($fg) => [
                    'group_number' => $fg->group_number,
                    'unit_code' => $fg->unit_code->value,
                    'tech_level' => $fg->tech_level,
                    'quantity' => $fg->quantity,
                    'orders_unit' => $fg->orders_unit->value,
                    'orders_tech_level' => $fg->orders_tech_level,
                    'wip' => $fg->wip->map(fn ($w) => [
                        'quarter' => $w->quarter,
                        'unit_code' => $w->unit_code->value,
                        'tech_level' => $w->tech_level,
                        'quantity' => $w->quantity,
                    ])->values(),
                ])->values(),
                'farm_groups' => $colony->farmGroups->map(fn ($fg) => [
                    'group_number' => $fg->group_number,
                    'unit_code' => $fg->unit_code->value,
                    'tech_level' => $fg->tech_level,
                    'quantity' => $fg->quantity,
                ])->values(),
                'mine_groups' => $colony->mineGroups->map(fn ($mg) => [
                    'deposit_id' => $mg->deposit_id,
                    'resource' => $mg->resource->value,
                    'quantity_remaining' => $mg->quantity_remaining,
                    'yield_pct' => $mg->yield_pct,
                    'unit_code' => $mg->unit_code->value,
                    'tech_level' => $mg->tech_level,
                    'quantity' => $mg->quantity,
                ])->values(),
                'population' => $colony->population->map(function ($pop) use ($colony) {
                    $cadres = [PopulationClass::ConstructionWorker->value, PopulationClass::Spy->value];
                    $isCadre = in_array($pop->population_code->value, $cadres);
                    $population = $isCadre ? $pop->quantity * 2 : $pop->quantity;
                    $cngdPaid = (int) ceil($pop->quantity * $pop->pay_rate);
                    $foodConsumed = (int) ceil($population * $colony->rations * 0.25);

                    return [
                        'population_code' => $pop->population_code->value,
                        'quantity' => $pop->quantity,
                        'population' => $population,
                        'employed' => $pop->employed,
                        'pay_rate' => $pop->pay_rate,
                        'cngd_paid' => $cngdPaid,
                        'ration_pct' => $colony->rations * 100,
                        'food_consumed' => $foodConsumed,
                        'rebel_quantity' => $pop->rebel_quantity,
                    ];
                })->values(),
            ])->values(),
            'surveys' => $report->surveys->map(fn ($survey) => [
                'id' => $survey->id,
                'planet_id' => $survey->planet_id,
                'orbit' => $survey->orbit,
                'star_x' => $survey->star_x,
                'star_y' => $survey->star_y,
                'star_z' => $survey->star_z,
                'star_sequence' => $survey->star_sequence,
                'planet_type' => $survey->planet_type->value,
                'habitability' => $survey->habitability,
                'deposits' => $survey->deposits->map(fn ($dep) => [
                    'deposit_no' => $dep->deposit_no,
                    'resource' => $dep->resource->value,
                    'yield_pct' => $dep->yield_pct,
                    'quantity_remaining' => $dep->quantity_remaining,
                ])->values(),
            ])->values(),
        ];
    }
}
