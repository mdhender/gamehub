<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Enums\PlanetType;
use App\Http\Requests\UploadColonyTemplateRequest;
use App\Http\Requests\UploadHomeSystemTemplateRequest;
use App\Models\Empire;
use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\Planet;
use App\Models\Player;
use App\Models\Star;
use App\Services\DepositGenerator;
use App\Services\EmpireCreator;
use App\Services\HomeSystemCreator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GameGenerationController extends Controller
{
    public function show(Game $game): Response
    {
        Gate::authorize('update', $game);

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
                'capacity' => 25,
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

    public function generateStars(Request $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canGenerateStars()) {
            throw ValidationException::withMessages([
                'seed' => 'Stars can only be generated when the game is in setup status.',
            ]);
        }

        $request->validate([
            'seed' => ['nullable', 'string', 'max:255'],
        ]);

        $seed = $request->filled('seed') ? $request->string('seed')->toString() : null;

        app(StarGenerator::class)->generate($game, $seed);

        return back()->with('success', 'Stars generated successfully.');
    }

    public function generatePlanets(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canGeneratePlanets()) {
            throw ValidationException::withMessages([
                'planets' => 'Planets can only be generated when the game is in stars generated status.',
            ]);
        }

        app(PlanetGenerator::class)->generate($game);

        return back()->with('success', 'Planets generated successfully.');
    }

    public function generateDeposits(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canGenerateDeposits()) {
            throw ValidationException::withMessages([
                'deposits' => 'Deposits can only be generated when the game is in planets generated status.',
            ]);
        }

        app(DepositGenerator::class)->generate($game);

        return back()->with('success', 'Deposits generated successfully.');
    }

    public function activate(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canActivate()) {
            throw ValidationException::withMessages([
                'game' => 'The game can only be activated when at least one home system has been created.',
            ]);
        }

        $game->status = GameStatus::Active;
        $game->save();

        return back()->with('success', 'Game activated.');
    }

    public function createHomeSystemRandom(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canCreateHomeSystems()) {
            throw ValidationException::withMessages([
                'home_system' => 'Home systems can only be created when deposits have been generated.',
            ]);
        }

        try {
            app(HomeSystemCreator::class)->createRandom($game);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'home_system' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Home system created.');
    }

    public function createHomeSystemManual(Request $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canCreateHomeSystems()) {
            throw ValidationException::withMessages([
                'home_system' => 'Home systems can only be created when deposits have been generated.',
            ]);
        }

        $validated = $request->validate([
            'star_id' => ['required', 'integer', 'exists:stars,id'],
        ]);

        $star = Star::findOrFail($validated['star_id']);

        if ($star->game_id !== $game->id) {
            abort(404);
        }

        try {
            app(HomeSystemCreator::class)->createManual($game, $star);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'star_id' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Home system created.');
    }

    public function createEmpire(Request $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canAssignEmpires()) {
            throw ValidationException::withMessages([
                'empire' => 'Empires can only be assigned when the game is active.',
            ]);
        }

        $validated = $request->validate([
            'player_id' => ['required', 'integer'],
            'home_system_id' => ['nullable', 'integer', 'exists:home_systems,id'],
        ]);

        $player = Player::findOrFail($validated['player_id']);

        if ($player->game_id !== $game->id) {
            abort(404);
        }

        $homeSystem = isset($validated['home_system_id'])
            ? HomeSystem::findOrFail($validated['home_system_id'])
            : null;

        if ($homeSystem && $homeSystem->game_id !== $game->id) {
            abort(404);
        }

        try {
            app(EmpireCreator::class)->create($game, $player->user, $homeSystem);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'empire' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Empire assigned.');
    }

    public function reassignEmpire(Request $request, Game $game, Empire $empire): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canAssignEmpires()) {
            throw ValidationException::withMessages([
                'empire' => 'Empires can only be reassigned when the game is active.',
            ]);
        }

        if ($empire->game_id !== $game->id) {
            abort(404);
        }

        $validated = $request->validate([
            'home_system_id' => ['required', 'integer', 'exists:home_systems,id'],
        ]);

        $homeSystem = HomeSystem::findOrFail($validated['home_system_id']);

        if ($homeSystem->game_id !== $game->id) {
            abort(404);
        }

        try {
            app(EmpireCreator::class)->reassign($empire, $homeSystem);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'home_system_id' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Empire reassigned.');
    }

    public function updateStar(Request $request, Game $game, Star $star): RedirectResponse
    {
        Gate::authorize('update', $game);

        if ($star->game_id !== $game->id) {
            abort(404);
        }

        if (! $game->isStarsGenerated()) {
            throw ValidationException::withMessages([
                'star' => 'Stars can only be edited when the game is in stars generated status.',
            ]);
        }

        $validated = $request->validate([
            'x' => ['required', 'integer', 'min:0', 'max:30'],
            'y' => ['required', 'integer', 'min:0', 'max:30'],
            'z' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        $x = (int) $validated['x'];
        $y = (int) $validated['y'];
        $z = (int) $validated['z'];

        if ($x !== $star->x || $y !== $star->y || $z !== $star->z) {
            $star->sequence = $game->stars()
                ->where('x', $x)
                ->where('y', $y)
                ->where('z', $z)
                ->where('id', '!=', $star->id)
                ->count() + 1;
        }

        $star->x = $x;
        $star->y = $y;
        $star->z = $z;
        $star->save();

        return back()->with('success', 'Star updated.');
    }

    public function updatePlanet(Request $request, Game $game, Planet $planet): RedirectResponse
    {
        Gate::authorize('update', $game);

        if ($planet->game_id !== $game->id) {
            abort(404);
        }

        if (! $game->isPlanetsGenerated()) {
            throw ValidationException::withMessages([
                'planet' => 'Planets can only be edited when the game is in planets generated status.',
            ]);
        }

        $validated = $request->validate([
            'orbit' => ['required', 'integer', 'min:1', 'max:11'],
            'type' => ['required', Rule::enum(PlanetType::class)],
            'habitability' => ['required', 'integer', 'min:0', 'max:25'],
        ]);

        $planet->update($validated);

        return back()->with('success', 'Planet updated.');
    }

    public function deleteStep(Game $game, string $step): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! in_array($step, ['stars', 'planets', 'deposits', 'home_systems'], true)) {
            abort(404);
        }

        if (! $game->canDeleteStep()) {
            throw ValidationException::withMessages([
                'step' => 'Steps cannot be deleted at the current game status.',
            ]);
        }

        DB::transaction(function () use ($game, $step) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            match ($step) {
                'home_systems' => $this->performDeleteHomeSystems($game),
                'deposits' => $this->performDeleteDeposits($game),
                'planets' => $this->performDeletePlanets($game),
                'stars' => $this->performDeleteStars($game),
            };
        });

        return back()->with('success', ucwords(str_replace('_', ' ', $step)).' deleted successfully.');
    }

    private function performDeleteHomeSystems(Game $game): void
    {
        $depositStep = $game->generationSteps()
            ->where('step', GenerationStepName::Deposits->value)
            ->first();

        $game->homeSystems()->delete();
        $game->generationSteps()->where('step', GenerationStepName::HomeSystem->value)->delete();

        $game->prng_state = $depositStep?->output_state;
        $game->status = GameStatus::DepositsGenerated;
        $game->save();
    }

    private function performDeleteDeposits(Game $game): void
    {
        $planetStep = $game->generationSteps()
            ->where('step', GenerationStepName::Planets->value)
            ->first();

        $game->homeSystems()->delete();
        $game->deposits()->delete();
        $game->generationSteps()
            ->whereIn('step', [GenerationStepName::HomeSystem->value, GenerationStepName::Deposits->value])
            ->delete();

        $game->prng_state = $planetStep?->output_state;
        $game->status = GameStatus::PlanetsGenerated;
        $game->save();
    }

    private function performDeletePlanets(Game $game): void
    {
        $starStep = $game->generationSteps()
            ->where('step', GenerationStepName::Stars->value)
            ->first();

        $game->homeSystems()->delete();
        $game->deposits()->delete();
        $game->planets()->delete();
        $game->generationSteps()
            ->whereIn('step', [
                GenerationStepName::HomeSystem->value,
                GenerationStepName::Deposits->value,
                GenerationStepName::Planets->value,
            ])
            ->delete();

        $game->prng_state = $starStep?->output_state;
        $game->status = GameStatus::StarsGenerated;
        $game->save();
    }

    private function performDeleteStars(Game $game): void
    {
        $game->homeSystems()->delete();
        $game->deposits()->delete();
        $game->planets()->delete();
        $game->stars()->delete();
        $game->generationSteps()->delete();

        $game->prng_state = null;
        $game->status = GameStatus::Setup;
        $game->save();
    }

    public function uploadHomeSystemTemplate(UploadHomeSystemTemplateRequest $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if ($game->isActive()) {
            throw ValidationException::withMessages([
                'template' => 'Templates cannot be modified for an active game.',
            ]);
        }

        $data = json_decode(file_get_contents($request->file('template')->getRealPath()), true);

        if (empty($data['planets']) || ! is_array($data['planets'])) {
            throw ValidationException::withMessages([
                'template' => 'Template must have at least one planet.',
            ]);
        }

        $homeworldCount = collect($data['planets'])->filter(fn ($p) => $p['homeworld'] ?? false)->count();

        if ($homeworldCount !== 1) {
            throw ValidationException::withMessages([
                'template' => 'Template must have exactly one homeworld planet.',
            ]);
        }

        $game->homeSystemTemplate()->delete();

        $template = $game->homeSystemTemplate()->create();

        foreach ($data['planets'] as $planetData) {
            $planet = $template->planets()->create([
                'orbit' => $planetData['orbit'],
                'type' => $planetData['type'],
                'habitability' => $planetData['habitability'],
                'is_homeworld' => $planetData['homeworld'] ?? false,
            ]);

            foreach ($planetData['deposits'] ?? [] as $depositData) {
                $planet->deposits()->create([
                    'resource' => $depositData['resource'],
                    'yield_pct' => $depositData['yield_pct'],
                    'quantity_remaining' => $depositData['quantity_remaining'],
                ]);
            }
        }

        return back()->with('success', 'Home system template uploaded.');
    }

    public function uploadColonyTemplate(UploadColonyTemplateRequest $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if ($game->isActive()) {
            throw ValidationException::withMessages([
                'template' => 'Templates cannot be modified for an active game.',
            ]);
        }

        $raw = json_decode(file_get_contents($request->file('template')->getRealPath()), true);
        $data = array_change_key_case($raw, CASE_LOWER);
        $inventory = $data['inventory'] ?? [];

        if (empty($inventory) || ! is_array($inventory)) {
            throw ValidationException::withMessages([
                'template' => 'Template must have at least one inventory item.',
            ]);
        }

        $game->colonyTemplate()->delete();

        $template = $game->colonyTemplate()->create([
            'kind' => $data['kind'],
            'tech_level' => $data['techlevel'],
        ]);

        foreach ($inventory as $itemData) {
            $item = array_change_key_case($itemData, CASE_LOWER);
            $template->items()->create([
                'unit' => $item['unit'],
                'tech_level' => $item['techlevel'],
                'quantity_assembled' => $item['quantityassembled'],
                'quantity_disassembled' => $item['quantitydisassembled'],
            ]);
        }

        return back()->with('success', 'Colony template uploaded.');
    }
}
