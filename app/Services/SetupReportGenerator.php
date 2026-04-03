<?php

namespace App\Services;

use App\Enums\TurnStatus;
use App\Models\Empire;
use App\Models\Turn;
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
                // Tasks F11 and F12 will implement snapshotEmpire()
            }

            $turn->update(['status' => TurnStatus::Completed]);

            return $empires->count();
        });
    }
}
