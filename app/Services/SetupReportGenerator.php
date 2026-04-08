<?php

namespace App\Services;

use App\Enums\TurnStatus;
use App\Models\Empire;
use App\Models\Turn;
use App\Models\TurnReport;
use Illuminate\Support\Facades\DB;

class SetupReportGenerator
{
    /**
     * Generate setup reports for all empires with colonies.
     *
     * @throws \RuntimeException if the turn is not available for report generation.
     */
    public function generate(Turn $turn): int
    {
        return DB::transaction(function () use ($turn) {
            $updated = Turn::where('id', $turn->id)
                ->whereNull('reports_locked_at')
                ->whereIn('status', [TurnStatus::Pending, TurnStatus::Completed])
                ->update(['status' => TurnStatus::Generating]);

            if ($updated === 0) {
                throw new \RuntimeException('Turn is not available for report generation.');
            }

            $turn = Turn::findOrFail($turn->id);

            $empires = Empire::where('game_id', $turn->game_id)
                ->whereHas('colonies')
                ->with([
                    'colonies' => fn ($q) => $q->orderBy('id'),
                    'colonies.star',
                    'colonies.planet',
                    'colonies.inventory',
                    'colonies.population',
                    'colonies.mineGroups.deposit',
                    'colonies.mineGroups.units',
                    'colonies.farmGroups.units',
                    'homeSystem.homeworldPlanet.star',
                    'homeSystem.homeworldPlanet.deposits' => fn ($q) => $q->orderBy('id'),
                ])
                ->orderBy('id')
                ->get();

            $generatedAt = now();

            foreach ($empires as $empire) {
                TurnReport::where('turn_id', $turn->id)
                    ->where('empire_id', $empire->id)
                    ->delete();

                $report = TurnReport::create([
                    'game_id' => $turn->game_id,
                    'turn_id' => $turn->id,
                    'empire_id' => $empire->id,
                    'generated_at' => $generatedAt,
                ]);

                foreach ($empire->colonies as $colony) {
                    $star = $colony->star;

                    $reportColony = $report->colonies()->create([
                        'source_colony_id' => $colony->id,
                        'name' => $colony->name,
                        'kind' => $colony->kind,
                        'tech_level' => $colony->tech_level,
                        'planet_id' => $colony->planet_id,
                        'orbit' => $colony->planet?->orbit ?? 0,
                        'star_x' => $star->x,
                        'star_y' => $star->y,
                        'star_z' => $star->z,
                        'star_sequence' => $star->sequence,
                        'rations' => $colony->rations,
                        'sol' => $colony->sol,
                        'birth_rate' => $colony->birth_rate,
                        'death_rate' => $colony->death_rate,
                    ]);

                    foreach ($colony->inventory as $item) {
                        $reportColony->inventory()->create([
                            'unit_code' => $item->unit,
                            'tech_level' => $item->tech_level,
                            'inventory_section' => $item->inventory_section,
                            'quantity' => $item->quantity,
                        ]);
                    }

                    foreach ($colony->population as $pop) {
                        $reportColony->population()->create([
                            'population_code' => $pop->population_code,
                            'quantity' => $pop->quantity,
                            'employed' => 0,
                            'pay_rate' => $pop->pay_rate,
                            'rebel_quantity' => $pop->rebel_quantity,
                        ]);
                    }

                    foreach ($colony->mineGroups as $mineGroup) {
                        $deposit = $mineGroup->deposit;

                        foreach ($mineGroup->units as $unit) {
                            $reportColony->mineGroups()->create([
                                'deposit_id' => $deposit->id,
                                'resource' => $deposit->resource,
                                'quantity_remaining' => $deposit->quantity_remaining,
                                'yield_pct' => $deposit->yield_pct,
                                'unit_code' => $unit->unit,
                                'tech_level' => $unit->tech_level,
                                'quantity' => $unit->quantity,
                            ]);
                        }
                    }

                    foreach ($colony->farmGroups as $farmGroup) {
                        $aggregated = $farmGroup->units
                            ->groupBy(fn ($u) => $u->unit->value.'|'.$u->tech_level)
                            ->map(fn ($units) => [
                                'unit' => $units->first()->unit,
                                'tech_level' => $units->first()->tech_level,
                                'quantity' => $units->sum('quantity'),
                            ]);

                        foreach ($aggregated as $agg) {
                            $reportColony->farmGroups()->create([
                                'group_number' => $farmGroup->group_number,
                                'unit_code' => $agg['unit'],
                                'tech_level' => $agg['tech_level'],
                                'quantity' => $agg['quantity'],
                            ]);
                        }
                    }
                }

                $homeworld = $empire->homeSystem->homeworldPlanet;
                $homeworldStar = $homeworld->star;

                $survey = $report->surveys()->create([
                    'planet_id' => $homeworld->id,
                    'orbit' => $homeworld->orbit,
                    'star_x' => $homeworldStar->x,
                    'star_y' => $homeworldStar->y,
                    'star_z' => $homeworldStar->z,
                    'star_sequence' => $homeworldStar->sequence,
                    'planet_type' => $homeworld->type,
                    'habitability' => $homeworld->habitability,
                ]);

                $homeworld->deposits->values()->each(function ($deposit, $index) use ($survey) {
                    $survey->deposits()->create([
                        'deposit_no' => $index + 1,
                        'resource' => $deposit->resource,
                        'yield_pct' => $deposit->yield_pct,
                        'quantity_remaining' => $deposit->quantity_remaining,
                    ]);
                });
            }

            $turn->update(['status' => TurnStatus::Completed]);

            return $empires->count();
        });
    }
}
