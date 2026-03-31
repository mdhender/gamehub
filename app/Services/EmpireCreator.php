<?php

namespace App\Services;

use App\Models\Colony;
use App\Models\ColonyInventory;
use App\Models\Empire;
use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmpireCreator
{
    /**
     * Assign an empire to a player in the game.
     *
     * If $homeSystem is provided, assigns the empire to that specific home system (if not full).
     * Otherwise, assigns to the first home system in queue order that has capacity.
     *
     * @throws \RuntimeException for all business-rule violations.
     */
    public function create(Game $game, User $player, ?HomeSystem $homeSystem = null): Empire
    {
        return DB::transaction(function () use ($game, $player, $homeSystem) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            if ($game->empires()->count() >= 250) {
                throw new \RuntimeException('This game has reached the maximum of 250 empires.');
            }

            $pivot = $game->users()->where('user_id', $player->id)->first()?->pivot;

            if (! $pivot || ! $pivot->is_active) {
                throw new \RuntimeException('This member is not an active player in this game.');
            }

            if ($game->empires()->where('game_user_id', $player->id)->exists()) {
                throw new \RuntimeException('This player already has an empire in this game.');
            }

            if ($homeSystem !== null) {
                if ($homeSystem->empires()->count() >= 25) {
                    throw new \RuntimeException('This home system is at full capacity (25 empires).');
                }
            } else {
                $homeSystem = $game->homeSystems()
                    ->withCount('empires')
                    ->get()
                    ->first(fn (HomeSystem $hs) => $hs->empires_count < 25);

                if ($homeSystem === null) {
                    throw new \RuntimeException('No home system has remaining capacity. Create a new home system to continue.');
                }
            }

            $empire = Empire::create([
                'game_id' => $game->id,
                'game_user_id' => $player->id,
                'name' => $player->name."'s Empire",
                'home_system_id' => $homeSystem->id,
            ]);

            $this->createColony($empire, $homeSystem, $game);

            return $empire;
        });
    }

    /**
     * Move an empire to a different home system and relocate its colony to the new homeworld.
     *
     * @throws \RuntimeException if the target home system is at full capacity.
     */
    public function reassign(Empire $empire, HomeSystem $homeSystem): Empire
    {
        return DB::transaction(function () use ($empire, $homeSystem) {
            if ($empire->home_system_id === $homeSystem->id) {
                return $empire;
            }

            if ($homeSystem->empires()->where('id', '!=', $empire->id)->count() >= 25) {
                throw new \RuntimeException('This home system is at full capacity (25 empires).');
            }

            $empire->colonies()
                ->where('planet_id', $empire->homeSystem->homeworld_planet_id)
                ->update(['planet_id' => $homeSystem->homeworld_planet_id]);

            $empire->home_system_id = $homeSystem->id;
            $empire->save();

            return $empire;
        });
    }

    private function createColony(Empire $empire, HomeSystem $homeSystem, Game $game): Colony
    {
        $colonyTemplate = $game->colonyTemplate()->with('items')->first();

        if (! $colonyTemplate) {
            throw new \RuntimeException('This game does not have a colony template.');
        }

        $colony = Colony::create([
            'empire_id' => $empire->id,
            'planet_id' => $homeSystem->homeworld_planet_id,
            'kind' => $colonyTemplate->kind,
            'tech_level' => $colonyTemplate->tech_level,
        ]);

        foreach ($colonyTemplate->items as $item) {
            ColonyInventory::create([
                'colony_id' => $colony->id,
                'unit' => $item->unit,
                'tech_level' => $item->tech_level,
                'quantity_assembled' => $item->quantity_assembled,
                'quantity_disassembled' => $item->quantity_disassembled,
            ]);
        }

        return $colony;
    }
}
