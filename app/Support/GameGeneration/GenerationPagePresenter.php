<?php

namespace App\Support\GameGeneration;

use App\Models\Game;
use App\Models\HomeSystem;
use Illuminate\Support\Facades\DB;

class GenerationPagePresenter
{
    public function __construct(
        private Game $game,
    ) {}

    /** @return array<string, mixed> */
    public function gamePayload(): array
    {
        return [
            ...$this->game->only('id', 'name', 'prng_seed', 'min_home_system_distance'),
            'status' => $this->game->status->value,
            'can_edit_templates' => $this->game->canEditTemplates(),
            'can_generate_stars' => $this->game->canGenerateStars(),
            'can_generate_planets' => $this->game->canGeneratePlanets(),
            'can_generate_deposits' => $this->game->canGenerateDeposits(),
            'can_create_home_systems' => $this->game->canCreateHomeSystems(),
            'can_delete_step' => $this->game->canDeleteStep(),
            'can_activate' => $this->game->canActivate(),
            'can_assign_empires' => $this->game->canAssignEmpires(),
        ];
    }

    /** @return array{planet_count: int, homeworld_orbit: int|null, deposit_summary: array<string, int>}|null */
    public function homeSystemTemplateSummary(): ?array
    {
        $template = $this->game->homeSystemTemplate?->load('planets.deposits');

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

    /** @return list<array{unit_count: int, kind: string, tech_level: int}>|null */
    public function colonyTemplateSummary(): ?array
    {
        $templates = $this->game->colonyTemplates()->with('items')->get();

        if ($templates->isEmpty()) {
            return null;
        }

        return $templates->map(fn ($template) => [
            'unit_count' => $template->items->count(),
            'kind' => $template->kind,
            'tech_level' => $template->tech_level,
        ])->values()->all();
    }

    /** @return array{count: int, system_count: int}|null */
    public function starsSummary(): ?array
    {
        if ($this->game->isSetup()) {
            return null;
        }

        $starCoords = $this->game->stars()->select(['x', 'y', 'z'])->get();

        return [
            'count' => $starCoords->count(),
            'system_count' => $starCoords->unique(fn ($s) => "{$s->x}-{$s->y}-{$s->z}")->count(),
        ];
    }

    /** @return array{count: int, by_type: array<string, int>}|null */
    public function planetsSummary(): ?array
    {
        if ($this->game->isSetup() || $this->game->isStarsGenerated()) {
            return null;
        }

        return [
            'count' => $this->game->planets()->count(),
            'by_type' => $this->game->planets()
                ->select('type', DB::raw('COUNT(*) as cnt'))
                ->groupBy('type')
                ->get()
                ->mapWithKeys(fn ($p) => [$p->type->value => (int) $p->cnt])
                ->toArray(),
        ];
    }

    /** @return array{count: int}|null */
    public function depositsSummary(): ?array
    {
        if (! ($this->game->isDepositsGenerated() || $this->game->isHomeSystemGenerated() || $this->game->isActive())) {
            return null;
        }

        return ['count' => $this->game->deposits()->count()];
    }

    /** @return list<array{id: int, x: int, y: int, z: int, sequence: int, location: string}>|null */
    public function starList(): ?array
    {
        if (! $this->game->isStarsGenerated()) {
            return null;
        }

        return $this->game->stars()
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

    /** @return list<array<string, mixed>>|null */
    public function planetList(): ?array
    {
        if (! $this->game->isPlanetsGenerated()) {
            return null;
        }

        return $this->game->planets()
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

    /** @return list<array{id: int, queue_position: int, star_location: string, empire_count: int, capacity: int}> */
    public function homeSystemsList(): array
    {
        return $this->game->homeSystems()
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

    /** @return list<array{id: int, location: string}>|null */
    public function availableStarsList(): ?array
    {
        if (! $this->game->canCreateHomeSystems()) {
            return null;
        }

        $usedStarIds = $this->game->homeSystems()->pluck('star_id');

        return $this->game->stars()
            ->whereNotIn('id', $usedStarIds)
            ->orderBy('x')->orderBy('y')->orderBy('z')->orderBy('sequence')
            ->get(['id', 'x', 'y', 'z', 'sequence'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'location' => $s->location(),
            ])
            ->all();
    }
}
