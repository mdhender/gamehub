<?php

namespace App\Http\Controllers\GameGeneration;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadColonyTemplateRequest;
use App\Http\Requests\UploadHomeSystemTemplateRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
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

        $raw = json_decode(file_get_contents($request->file('template')->getRealPath()), true);
        $data = array_change_key_case($raw, CASE_LOWER);
        $inventory = $data['inventory'] ?? [];

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
