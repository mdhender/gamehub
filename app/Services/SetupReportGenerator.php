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
                    'colonies.planet.star',
                    'colonies.inventory',
                    'colonies.population',
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
                    $star = $colony->planet->star;

                    $reportColony = $report->colonies()->create([
                        'source_colony_id' => $colony->id,
                        'name' => $colony->name,
                        'kind' => $colony->kind,
                        'tech_level' => $colony->tech_level,
                        'planet_id' => $colony->planet_id,
                        'orbit' => $colony->planet->orbit,
                        'star_x' => $star->x,
                        'star_y' => $star->y,
                        'star_z' => $star->z,
                        'star_sequence' => $star->sequence,
                        'is_on_surface' => $colony->is_on_surface,
                        'rations' => $colony->rations,
                        'sol' => $colony->sol,
                        'birth_rate' => $colony->birth_rate,
                        'death_rate' => $colony->death_rate,
                    ]);

                    foreach ($colony->inventory as $item) {
                        $reportColony->inventory()->create([
                            'unit_code' => $item->unit,
                            'tech_level' => $item->tech_level,
                            'quantity_assembled' => $item->quantity_assembled,
                            'quantity_disassembled' => $item->quantity_disassembled,
                        ]);
                    }

                    foreach ($colony->population as $pop) {
                        $reportColony->population()->create([
                            'population_code' => $pop->population_code,
                            'quantity' => $pop->quantity,
                            'pay_rate' => $pop->pay_rate,
                            'rebel_quantity' => $pop->rebel_quantity,
                        ]);
                    }
                }

                // Task F12 will add homeworld survey snapshots here
            }

            $turn->update(['status' => TurnStatus::Completed]);

            return $empires->count();
        });
    }
}
