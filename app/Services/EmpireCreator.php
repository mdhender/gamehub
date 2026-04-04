<?php

namespace App\Services;

use App\Models\Colony;
use App\Models\ColonyInventory;
use App\Models\ColonyPopulation;
use App\Models\Empire;
use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\Planet;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EmpireCreator
{
    /**
     * Assign an empire to a player in the game.
     *
     * The Player record (not the User) is the owner of the empire. This preserves
     * per-game context: if a user is deactivated, their empire persists independently.
     *
     * If $homeSystem is provided, assigns the empire to that specific home system (if not full).
     * Otherwise, assigns to the first home system in queue order that has capacity.
     *
     * @throws \RuntimeException for all business-rule violations.
     */
    public function create(Game $game, User $user, ?HomeSystem $homeSystem = null): Empire
    {
        return DB::transaction(function () use ($game, $user, $homeSystem) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            if ($game->empires()->count() >= HomeSystem::MAX_EMPIRES_PER_GAME) {
                throw new \RuntimeException('This game has reached the maximum of '.HomeSystem::MAX_EMPIRES_PER_GAME.' empires.');
            }

            $player = Player::where('game_id', $game->id)
                ->where('user_id', $user->id)
                ->first();

            if (! $player || ! $player->is_active) {
                throw new \RuntimeException('This member is not an active player in this game.');
            }

            if ($player->empire()->exists()) {
                throw new \RuntimeException('This player already has an empire in this game.');
            }

            if ($homeSystem !== null) {
                if ($homeSystem->empires()->count() >= HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM) {
                    throw new \RuntimeException('This home system is at full capacity ('.HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM.' empires).');
                }
            } else {
                $homeSystem = $game->homeSystems()
                    ->withCount('empires')
                    ->get()
                    ->first(fn (HomeSystem $hs) => $hs->empires_count < HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM);

                if ($homeSystem === null) {
                    throw new \RuntimeException('No home system has remaining capacity. Create a new home system to continue.');
                }
            }

            $empire = Empire::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'name' => $user->name."'s Empire",
                'home_system_id' => $homeSystem->id,
            ]);

            $this->createColonies($empire, $homeSystem, $game);

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

            if ($homeSystem->empires()->where('id', '!=', $empire->id)->count() >= HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM) {
                throw new \RuntimeException('This home system is at full capacity ('.HomeSystem::MAX_EMPIRES_PER_HOME_SYSTEM.' empires).');
            }

            $newHomeworld = Planet::findOrFail($homeSystem->homeworld_planet_id);

            $empire->colonies()
                ->where('planet_id', $empire->homeSystem->homeworld_planet_id)
                ->update([
                    'star_id' => $newHomeworld->star_id,
                    'planet_id' => $newHomeworld->id,
                ]);

            $empire->home_system_id = $homeSystem->id;
            $empire->save();

            return $empire;
        });
    }

    private function createColonies(Empire $empire, HomeSystem $homeSystem, Game $game): void
    {
        $colonyTemplates = $game->colonyTemplates()->with(['items', 'population'])->orderBy('id')->get();

        if ($colonyTemplates->isEmpty()) {
            throw new \RuntimeException('This game does not have a colony template.');
        }

        $homeworldPlanet = Planet::findOrFail($homeSystem->homeworld_planet_id);

        foreach ($colonyTemplates as $colonyTemplate) {
            $colony = Colony::create([
                'empire_id' => $empire->id,
                'star_id' => $homeworldPlanet->star_id,
                'planet_id' => $homeworldPlanet->id,
                'kind' => $colonyTemplate->kind,
                'tech_level' => $colonyTemplate->tech_level,
            ]);

            if ($colonyTemplate->items->isNotEmpty()) {
                ColonyInventory::insert(
                    $colonyTemplate->items->map(fn ($item) => [
                        'colony_id' => $colony->id,
                        'unit' => $item->unit->value,
                        'tech_level' => $item->tech_level,
                        'quantity_assembled' => $item->quantity_assembled,
                        'quantity_disassembled' => $item->quantity_disassembled,
                    ])->all()
                );
            }

            if ($colonyTemplate->population->isNotEmpty()) {
                ColonyPopulation::insert(
                    $colonyTemplate->population->map(fn ($pop) => [
                        'colony_id' => $colony->id,
                        'population_code' => $pop->population_code->value,
                        'quantity' => $pop->quantity,
                        'pay_rate' => $pop->pay_rate,
                        'rebel_quantity' => 0,
                    ])->all()
                );
            }
        }
    }
}
