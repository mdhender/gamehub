<?php

namespace App\Http\Controllers;

use App\Actions\GameGeneration\ActivateGame;
use App\Models\Game;
use App\Support\GameGeneration\ClusterExporter;
use App\Support\GameGeneration\GenerationPagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GameGenerationController extends Controller
{
    public function show(Game $game): Response
    {
        Gate::authorize('update', $game);
        $game->load('generationSteps');
        $p = new GenerationPagePresenter($game);

        return Inertia::render('games/generate', [
            'game' => $p->gamePayload(),
            'homeSystemTemplate' => $p->homeSystemTemplateSummary(),

            'generationSteps' => $game->generationSteps->map(fn ($step) => [
                'id' => $step->id, 'step' => $step->step->value, 'sequence' => $step->sequence,
            ]),
            'stars' => $p->starsSummary(),
            'planets' => $p->planetsSummary(),
            'deposits' => $p->depositsSummary(),
            'starList' => Inertia::defer(fn () => $p->starList()),
            'planetList' => Inertia::defer(fn () => $p->planetList()),
            'homeSystems' => $p->homeSystemsList(),
            'availableStars' => $p->availableStarsList(),
        ]);
    }

    public function download(Game $game): \Symfony\Component\HttpFoundation\Response
    {
        Gate::authorize('update', $game);
        abort_if($game->isSetup(), 404);
        $json = json_encode((new ClusterExporter)->toArray($game), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="cluster-'.$game->id.'.json"',
        ]);
    }

    public function activate(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);
        (new ActivateGame)->execute($game);

        return redirect()->to(route('games.show', $game).'?tab=empires')->with('success', 'Game activated.');
    }
}
