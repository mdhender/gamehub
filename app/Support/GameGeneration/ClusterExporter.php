<?php

namespace App\Support\GameGeneration;

use App\Models\Game;

class ClusterExporter
{
    /**
     * Build the JSON-serializable payload for a cluster download.
     *
     * @return array<string, mixed>
     */
    public function toArray(Game $game): array
    {
        return [
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
    }
}
