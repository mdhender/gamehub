<?php

namespace App\Http\Controllers\GameGeneration;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadColonyTemplateRequest;
use App\Http\Requests\UploadHomeSystemTemplateRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function uploadHomeSystem(UploadHomeSystemTemplateRequest $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if ($game->isActive()) {
            throw ValidationException::withMessages([
                'template' => 'Templates cannot be modified for an active game.',
            ]);
        }

        $data = json_decode(file_get_contents($request->file('template')->getRealPath()), true);

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

    public function uploadColony(UploadColonyTemplateRequest $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if ($game->isActive()) {
            throw ValidationException::withMessages([
                'template' => 'Templates cannot be modified for an active game.',
            ]);
        }

        $templatesData = json_decode(file_get_contents($request->file('template')->getRealPath()), true);

        DB::transaction(function () use ($game, $templatesData) {
            $game->colonyTemplates()->delete();

            foreach ($templatesData as $templateData) {
                $template = $game->colonyTemplates()->create([
                    'kind' => $templateData['kind'],
                    'tech_level' => $templateData['tech-level'],
                ]);

                foreach ($templateData['population'] as $popData) {
                    $template->population()->create([
                        'population_code' => $popData['population_code'],
                        'quantity' => $popData['quantity'],
                        'pay_rate' => $popData['pay_rate'],
                    ]);
                }

                $allItems = array_merge(
                    $templateData['inventory']['operational'] ?? [],
                    $templateData['inventory']['stored'] ?? [],
                );

                foreach ($allItems as $itemData) {
                    $unit = $itemData['unit'];
                    if (str_contains($unit, '-')) {
                        [$unitCode, $techLevel] = explode('-', $unit, 2);
                        $techLevel = (int) $techLevel;
                    } else {
                        $unitCode = $unit;
                        $techLevel = 0;
                    }

                    $template->items()->create([
                        'unit' => $unitCode,
                        'tech_level' => $techLevel,
                        'quantity_assembled' => $itemData['quantity'],
                        'quantity_disassembled' => 0,
                    ]);
                }
            }
        });

        return back()->with('success', 'Colony template uploaded.');
    }
}
