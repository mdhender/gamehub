<?php

namespace App\Http\Controllers;

use App\Enums\TurnStatus;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Turn;
use App\Models\TurnReport;
use App\Services\SetupReportGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TurnReportController extends Controller
{
    public function generate(Game $game, Turn $turn, SetupReportGenerator $generator): RedirectResponse
    {
        Gate::authorize('generate', [TurnReport::class, $game]);

        if (! $game->isActive()) {
            throw ValidationException::withMessages([
                'game' => 'The game must be active to generate reports.',
            ]);
        }

        if ($turn->number !== 0) {
            throw ValidationException::withMessages([
                'turn' => 'Only Turn 0 setup reports can be generated in this version.',
            ]);
        }

        try {
            $count = $generator->generate($turn);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'turn' => $e->getMessage(),
            ]);
        }

        return back()->with('success', "Generated {$count} setup report(s).");
    }

    public function lock(Game $game, Turn $turn): RedirectResponse
    {
        Gate::authorize('lock', [TurnReport::class, $game]);

        if (! $game->isActive()) {
            throw ValidationException::withMessages([
                'game' => 'The game must be active to lock reports.',
            ]);
        }

        if ($turn->number !== 0) {
            throw ValidationException::withMessages([
                'turn' => 'Only Turn 0 can be locked in this version.',
            ]);
        }

        $updated = Turn::where('id', $turn->id)
            ->whereNull('reports_locked_at')
            ->whereNotIn('status', [TurnStatus::Closed, TurnStatus::Generating])
            ->update([
                'reports_locked_at' => now(),
                'status' => TurnStatus::Closed,
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'turn' => 'Turn cannot be locked (already locked, closed, or currently generating).',
            ]);
        }

        return back()->with('success', 'Turn reports locked.');
    }

    public function show(Game $game, Turn $turn, Empire $empire): View
    {
        abort_unless($empire->game_id === $game->id, 404);

        Gate::authorize('show', [TurnReport::class, $game, $empire]);

        $report = $this->loadReport($game, $turn, $empire);

        return view('turn-reports.show', [
            'game' => $game,
            'turn' => $turn,
            'empire' => $empire,
            'report' => $report,
        ]);
    }

    public function download(Game $game, Turn $turn, Empire $empire): Response
    {
        abort_unless($empire->game_id === $game->id, 404);

        Gate::authorize('download', [TurnReport::class, $game, $empire]);

        $report = $this->loadReport($game, $turn, $empire);

        $data = [
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
                    'quantity_assembled' => $item->quantity_assembled,
                    'quantity_disassembled' => $item->quantity_disassembled,
                ])->values(),
                'population' => $colony->population->map(fn ($pop) => [
                    'population_code' => $pop->population_code->value,
                    'quantity' => $pop->quantity,
                    'pay_rate' => $pop->pay_rate,
                    'rebel_quantity' => $pop->rebel_quantity,
                ])->values(),
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

        $filename = "report-{$game->id}-turn-{$turn->number}-empire-{$empire->id}.json";
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function loadReport(Game $game, Turn $turn, Empire $empire): TurnReport
    {
        return TurnReport::query()
            ->where('game_id', $game->id)
            ->where('turn_id', $turn->id)
            ->where('empire_id', $empire->id)
            ->with([
                'colonies' => fn ($q) => $q->orderBy('id'),
                'colonies.inventory' => fn ($q) => $q->orderBy('id'),
                'colonies.population' => fn ($q) => $q->orderBy('id'),
                'surveys' => fn ($q) => $q->orderBy('id'),
                'surveys.deposits' => fn ($q) => $q->orderBy('deposit_no'),
            ])
            ->firstOrFail();
    }
}
