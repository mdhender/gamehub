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

        $homeSystemTemplate = $game->homeSystemTemplate?->load('planets.deposits');
        $colonyTemplate = $game->colonyTemplate?->load('items');

        $stars = null;
        if (! $game->isSetup()) {
            $starCoords = $game->stars()->select(['x', 'y', 'z'])->get();
            $stars = [
                'count' => $starCoords->count(),
                'system_count' => $starCoords->unique(fn ($s) => "{$s->x}-{$s->y}-{$s->z}")->count(),
            ];
        }

        $planets = null;
        if (! $game->isSetup() && ! $game->isStarsGenerated()) {
            $planets = [
                'count' => $game->planets()->count(),
                'by_type' => $game->planets()
                    ->select('type', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('type')
                    ->get()
                    ->mapWithKeys(fn ($p) => [$p->type->value => (int) $p->cnt])
                    ->toArray(),
            ];
        }

        $deposits = null;
        if ($game->isDepositsGenerated() || $game->isHomeSystemGenerated() || $game->isActive()) {
            $deposits = ['count' => $game->deposits()->count()];
        }

        $starList = null;
        if ($game->isStarsGenerated()) {
            $starList = $game->stars()
                ->orderBy('x')->orderBy('y')->orderBy('z')->orderBy('sequence')
                ->get(['id', 'x', 'y', 'z', 'sequence'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'x' => $s->x,
                    'y' => $s->y,
                    'z' => $s->z,
                    'sequence' => $s->sequence,
                    'location' => $s->location(),
                ]);
        }

        $planetList = null;
        if ($game->isPlanetsGenerated()) {
            $planetList = $game->planets()
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
                ]);
        }

        $homeSystems = $game->homeSystems()
            ->with('star')
            ->withCount('empires')
            ->get()
            ->map(fn ($hs) => [
                'id' => $hs->id,
                'queue_position' => $hs->queue_position,
                'star_location' => $hs->star->location(),
                'empire_count' => $hs->empires_count,
                'capacity' => HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM,
            ]);

        $availableStars = null;
        if ($game->canCreateHomeSystems()) {
            $usedStarIds = $game->homeSystems()->pluck('star_id');
            $availableStars = $game->stars()
                ->whereNotIn('id', $usedStarIds)
                ->orderBy('x')->orderBy('y')->orderBy('z')->orderBy('sequence')
                ->get(['id', 'x', 'y', 'z', 'sequence'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'location' => $s->location(),
                ]);
        }

        $playersByUserId = $game->playerRecords()
            ->where('role', 'player')
            ->where('is_active', true)
            ->with('user')
            ->get();

        $empiresByPlayerId = $game->empires()->with('homeSystem.star')->get()->keyBy('player_id');

        $members = $playersByUserId->map(function ($player) use ($empiresByPlayerId) {
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
        });

        return Inertia::render('games/generate', [
            'game' => [
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
            ],
            'homeSystemTemplate' => $homeSystemTemplate ? [
                'planet_count' => $homeSystemTemplate->planets->count(),
                'homeworld_orbit' => $homeSystemTemplate->planets->firstWhere('is_homeworld', true)?->orbit,
                'deposit_summary' => $homeSystemTemplate->planets
                    ->flatMap(fn ($p) => $p->deposits)
                    ->groupBy(fn ($d) => $d->resource->value)
                    ->map(fn ($group) => $group->count())
                    ->toArray(),
            ] : null,
            'colonyTemplate' => $colonyTemplate ? [
                'unit_count' => $colonyTemplate->items->count(),
                'kind' => $colonyTemplate->kind,
                'tech_level' => $colonyTemplate->tech_level,
            ] : null,
            'generationSteps' => $game->generationSteps->map(fn ($step) => [
                'id' => $step->id,
                'step' => $step->step->value,
                'sequence' => $step->sequence,
            ]),
            'stars' => $stars,
            'planets' => $planets,
            'deposits' => $deposits,
            'starList' => $starList,
            'planetList' => $planetList,
            'homeSystems' => $homeSystems,
            'availableStars' => $availableStars,
            'members' => $members,
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
}
