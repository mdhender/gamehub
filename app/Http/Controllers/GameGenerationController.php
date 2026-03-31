<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadColonyTemplateRequest;
use App\Http\Requests\UploadHomeSystemTemplateRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
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

        return Inertia::render('games/generate', [
            'game' => [
                ...$game->only('id', 'name', 'prng_seed', 'status'),
                'can_edit_templates' => $game->canEditTemplates(),
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
