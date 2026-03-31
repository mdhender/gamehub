<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\HomeSystem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GameGenerationController extends Controller
{
    public function show(Game $game): Response
    {
        Gate::authorize('update', $game);

        $game->load('generationSteps');

        return Inertia::render('games/generate', [
            'game' => $this->gamePayload($game),
            'homeSystemTemplate' => $this->homeSystemTemplateSummary($game),
            'colonyTemplate' => $this->colonyTemplateSummary($game),
            'generationSteps' => $game->generationSteps->map(fn ($step) => [
                'id' => $step->id,
                'step' => $step->step->value,
                'sequence' => $step->sequence,
            ]),
            'stars' => $this->starsSummary($game),
            'planets' => $this->planetsSummary($game),
            'deposits' => $this->depositsSummary($game),
            'starList' => Inertia::defer(fn () => $this->starList($game)),
            'planetList' => Inertia::defer(fn () => $this->planetList($game)),
            'homeSystems' => $this->homeSystemsList($game),
            'availableStars' => $this->availableStarsList($game),
            'members' => $this->membersList($game),
        ]);
    }

    public function download(Game $game): \Symfony\Component\HttpFoundation\Response
    {
        Gate::authorize('update', $game);

        if ($game->isSetup()) {
            abort(404);
        }

        $data = [
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'status' => $game->status->value,
            ],
            'stars' => $game->stars()
                ->with(['planets' => fn ($q) => $q->orderBy('orbit'), 'planets.deposits'])
                ->orderBy('x')->orderBy('y')->orderBy('z')->orderBy('sequence')
                ->get()
                ->map(fn ($star) => [
                    'location' => $star->location(),
                    'x' => $star->x,
                    'y' => $star->y,
                    'z' => $star->z,
                    'sequence' => $star->sequence,
                    'planets' => $star->planets->map(fn ($planet) => [
                        'orbit' => $planet->orbit,
                        'type' => $planet->type->value,
                        'habitability' => $planet->habitability,
                        'is_homeworld' => $planet->is_homeworld,
                        'deposits' => $planet->deposits->map(fn ($deposit) => [
                            'resource' => $deposit->resource->value,
                            'yield_pct' => $deposit->yield_pct,
                            'quantity_remaining' => $deposit->quantity_remaining,
                        ])->values(),
                    ])->values(),
                ])
                ->values(),
        ];

        $filename = 'cluster-'.$game->id.'.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function activate(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        DB::transaction(function () use ($game) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            if (! $game->canActivate()) {
                throw ValidationException::withMessages([
                    'game' => 'The game can only be activated when at least one home system has been created.',
                ]);
            }

            $game->status = GameStatus::Active;
            $game->save();
        });

        return back()->with('success', 'Game activated.');
    }

    private function gamePayload(Game $game): array
    {
        return [
            ...$game->only('id', 'name', 'prng_seed', 'min_home_system_distance'),
            'status' => $game->status->value,
            'can_edit_templates' => $game->canEditTemplates(),
            'can_generate_stars' => $game->canGenerateStars(),
            'can_generate_planets' => $game->canGeneratePlanets(),
            'can_generate_deposits' => $game->canGenerateDeposits(),
            'can_create_home_systems' => $game->canCreateHomeSystems(),
            'can_delete_step' => $game->canDeleteStep(),
            'can_activate' => $game->canActivate(),
            'can_assign_empires' => $game->canAssignEmpires(),
        ];
    }

    private function homeSystemTemplateSummary(Game $game): ?array
    {
        $template = $game->homeSystemTemplate?->load('planets.deposits');

        if (! $template) {
            return null;
        }

        return [
            'planet_count' => $template->planets->count(),
            'homeworld_orbit' => $template->planets->firstWhere('is_homeworld', true)?->orbit,
            'deposit_summary' => $template->planets
                ->flatMap(fn ($p) => $p->deposits)
                ->groupBy(fn ($d) => $d->resource->value)
                ->map(fn ($group) => $group->count())
                ->toArray(),
        ];
    }

    private function colonyTemplateSummary(Game $game): ?array
    {
        $template = $game->colonyTemplate?->load('items');

        if (! $template) {
            return null;
        }

        return [
            'unit_count' => $template->items->count(),
            'kind' => $template->kind,
            'tech_level' => $template->tech_level,
        ];
    }

    private function starsSummary(Game $game): ?array
    {
        if ($game->isSetup()) {
            return null;
        }

        $starCoords = $game->stars()->select(['x', 'y', 'z'])->get();

        return [
            'count' => $starCoords->count(),
            'system_count' => $starCoords->unique(fn ($s) => "{$s->x}-{$s->y}-{$s->z}")->count(),
        ];
    }

    private function planetsSummary(Game $game): ?array
    {
        if ($game->isSetup() || $game->isStarsGenerated()) {
            return null;
        }

        return [
            'count' => $game->planets()->count(),
            'by_type' => $game->planets()
                ->select('type', DB::raw('COUNT(*) as cnt'))
                ->groupBy('type')
                ->get()
                ->mapWithKeys(fn ($p) => [$p->type->value => (int) $p->cnt])
                ->toArray(),
        ];
    }

    private function depositsSummary(Game $game): ?array
    {
        if (! ($game->isDepositsGenerated() || $game->isHomeSystemGenerated() || $game->isActive())) {
            return null;
        }

        return ['count' => $game->deposits()->count()];
    }

    private function starList(Game $game): ?array
    {
        if (! $game->isStarsGenerated()) {
            return null;
        }

        return $game->stars()
            ->orderBy('x')->orderBy('y')->orderBy('z')->orderBy('sequence')
            ->get(['id', 'x', 'y', 'z', 'sequence'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'x' => $s->x,
                'y' => $s->y,
                'z' => $s->z,
                'sequence' => $s->sequence,
                'location' => $s->location(),
            ])
            ->all();
    }

    private function planetList(Game $game): ?array
    {
        if (! $game->isPlanetsGenerated()) {
            return null;
        }

        return $game->planets()
            ->with(['star:id,x,y,z'])
            ->orderBy('star_id')->orderBy('orbit')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'star_id' => $p->star_id,
                'star_location' => $p->star->location(),
                'orbit' => $p->orbit,
                'type' => $p->type->value,
                'habitability' => $p->habitability,
                'is_homeworld' => $p->is_homeworld,
            ])
            ->all();
    }

    private function homeSystemsList(Game $game): array
    {
        return $game->homeSystems()
            ->with('star')
            ->withCount('empires')
            ->get()
            ->map(fn ($hs) => [
                'id' => $hs->id,
                'queue_position' => $hs->queue_position,
                'star_location' => $hs->star->location(),
                'empire_count' => $hs->empires_count,
                'capacity' => HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM,
            ])
            ->all();
    }

    private function availableStarsList(Game $game): ?array
    {
        if (! $game->canCreateHomeSystems()) {
            return null;
        }

        $usedStarIds = $game->homeSystems()->pluck('star_id');

        return $game->stars()
            ->whereNotIn('id', $usedStarIds)
            ->orderBy('x')->orderBy('y')->orderBy('z')->orderBy('sequence')
            ->get(['id', 'x', 'y', 'z', 'sequence'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'location' => $s->location(),
            ])
            ->all();
    }

    private function membersList(Game $game): array
    {
        $players = $game->playerRecords()
            ->where('role', 'player')
            ->where('is_active', true)
            ->with('user')
            ->get();

        $empiresByPlayerId = $game->empires()->with('homeSystem.star')->get()->keyBy('player_id');

        return $players->map(function ($player) use ($empiresByPlayerId) {
            $empire = $empiresByPlayerId->get($player->id);

            return [
                'id' => $player->id,
                'user_id' => $player->user_id,
                'name' => $player->user->name,
                'empire' => $empire ? [
                    'id' => $empire->id,
                    'name' => $empire->name,
                    'home_system_id' => $empire->home_system_id,
                    'home_system_location' => $empire->homeSystem->star->location(),
                ] : null,
            ];
        })->all();
    }
}
