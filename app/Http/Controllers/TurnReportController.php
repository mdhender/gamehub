<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Turn;
use App\Models\TurnReport;
use App\Services\SetupReportGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
}
