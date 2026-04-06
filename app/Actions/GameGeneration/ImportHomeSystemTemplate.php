<?php

namespace App\Actions\GameGeneration;

use App\Models\Game;

class ImportHomeSystemTemplate
{
    /**
     * Import a home system template from the parsed JSON data.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(Game $game, array $data): void
    {
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
    }
}
