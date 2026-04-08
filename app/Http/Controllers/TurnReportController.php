<?php

namespace App\Http\Controllers;

use App\Enums\TurnStatus;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Turn;
use App\Models\TurnReport;
use App\Services\SetupReportGenerator;
use App\Support\TurnReports\TurnReportJsonExporter;
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
        Gate::authorize('show', [TurnReport::class, $game, $empire]);

        $report = $this->loadReport($game, $turn, $empire);

        return view('turn-reports.show', [
            'game' => $game,
            'turn' => $turn,
            'empire' => $empire,
            'report' => $report,
        ]);
    }

    public function download(Game $game, Turn $turn, Empire $empire, TurnReportJsonExporter $exporter): Response
    {
        Gate::authorize('download', [TurnReport::class, $game, $empire]);

        $report = $this->loadReport($game, $turn, $empire);
        $data = $exporter->toArray($game, $turn, $empire, $report);

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
                'colonies.farmGroups' => fn ($q) => $q->orderBy('group_number'),
                'colonies.mineGroups' => fn ($q) => $q->orderBy('deposit_id'),
                'surveys' => fn ($q) => $q->orderBy('id'),
                'surveys.deposits' => fn ($q) => $q->orderBy('deposit_no'),
            ])
            ->firstOrFail();
    }
}
