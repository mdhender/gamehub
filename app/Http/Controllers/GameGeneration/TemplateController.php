<?php

namespace App\Http\Controllers\GameGeneration;

use App\Actions\GameGeneration\ImportColonyTemplates;
use App\Actions\GameGeneration\ImportHomeSystemTemplate;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadColonyTemplateRequest;
use App\Http\Requests\UploadHomeSystemTemplateRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class TemplateController extends Controller
{
    public function uploadHomeSystem(UploadHomeSystemTemplateRequest $request, Game $game, ImportHomeSystemTemplate $action): RedirectResponse
    {
        if ($game->isActive()) {
            throw ValidationException::withMessages([
                'template' => 'Templates cannot be modified for an active game.',
            ]);
        }

        $action->execute($game, $request->templateData());

        return back()->with('success', 'Home system template uploaded.');
    }

    public function uploadColony(UploadColonyTemplateRequest $request, Game $game, ImportColonyTemplates $action): RedirectResponse
    {
        if ($game->isActive()) {
            throw ValidationException::withMessages([
                'template' => 'Templates cannot be modified for an active game.',
            ]);
        }

        $action->execute($game, $request->templateData());

        return back()->with('success', 'Colony template uploaded.');
    }
}
