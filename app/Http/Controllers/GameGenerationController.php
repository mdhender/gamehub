<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadColonyTemplateRequest;
use App\Http\Requests\UploadHomeSystemTemplateRequest;
use App\Models\Game;
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

        $empiresByUserId = $game->empires()->get()->keyBy('game_user_id');
        $members = $game->players()->get()->map(fn ($player) => [
            'id' => $player->id,
            'name' => $player->name,
            'empire' => ($empire = $empiresByUserId->get($player->id))
                ? ['id' => $empire->id, 'name' => $empire->name, 'home_system_id' => $empire->home_system_id]
                : null,
        ]);

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
            'homeSystems' => $homeSystems,
            'members' => $members,
        ]);
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
