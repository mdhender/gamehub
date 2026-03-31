<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\Planet;
use App\Models\Star;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameGenerationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeHomeSystemTemplateJson(int $planetCount = 1, int $homeworldCount = 1): string
    {
        $planets = [];

        for ($i = 0; $i < $planetCount; $i++) {
            $planets[] = [
                'orbit' => $i + 1,
                'type' => 'terrestrial',
                'habitability' => 10,
                'homeworld' => $homeworldCount > 0 && $i === 0,
                'deposits' => [
                    ['resource' => 'gold', 'yield_pct' => 5, 'quantity_remaining' => 1000],
                ],
            ];
        }

        if ($homeworldCount > 1) {
            $planets[1]['homeworld'] = true;
        }

        return json_encode(['planets' => $planets]);
    }

    private function makeColonyTemplateJson(int $itemCount = 1): string
    {
        $inventory = [];

        for ($i = 0; $i < $itemCount; $i++) {
            $inventory[] = [
                'unit' => $i + 1,
                'TechLevel' => 1,
                'QuantityAssembled' => 1000,
                'QuantityDisassembled' => 0,
            ];
        }

        return json_encode([
            'Kind' => 1,
            'TechLevel' => 1,
            'inventory' => $inventory,
        ]);
    }

    private function jsonFile(string $filename, string $content): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $filename, 'application/json', null, true);
    }

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    #[Test]
    public function generate_page_renders_for_authorized_user(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('games/generate')
                ->has('game')
                ->has('homeSystemTemplate')
                ->has('colonyTemplate')
            );
    }

    #[Test]
    public function generate_page_passes_template_summary_when_templates_exist(): void
    {
        $game = Game::factory()->withDefaultTemplates()->create();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('homeSystemTemplate.planet_count', 6)
                ->whereNot('homeSystemTemplate.homeworld_orbit', null)
                ->where('colonyTemplate.unit_count', 7)
            );
    }

    #[Test]
    public function generate_page_is_forbidden_for_non_gm(): void
    {
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertForbidden();
    }

    #[Test]
    public function generate_page_passes_all_required_props(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('generationSteps')
                ->has('stars')
                ->has('planets')
                ->has('deposits')
                ->has('homeSystems')
                ->has('members')
                ->has('game.min_home_system_distance')
                ->has('game.status')
                ->has('game.can_generate_stars')
                ->has('game.can_generate_planets')
                ->has('game.can_generate_deposits')
                ->has('game.can_create_home_systems')
                ->has('game.can_delete_step')
                ->has('game.can_activate')
                ->has('game.can_assign_empires')
            );
    }

    #[Test]
    public function generate_page_stars_is_null_at_setup(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stars', null));
    }

    #[Test]
    public function generate_page_stars_has_count_when_stars_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::StarsGenerated]);
        Star::factory()->count(3)->create(['game_id' => $game->id]);
        // Two stars at same coordinates form a system group
        Star::factory()->create(['game_id' => $game->id, 'x' => 0, 'y' => 0, 'z' => 0, 'sequence' => 1]);
        Star::factory()->create(['game_id' => $game->id, 'x' => 0, 'y' => 0, 'z' => 0, 'sequence' => 2]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stars.count', 5)
                ->where('stars.system_count', 4)
            );
    }

    #[Test]
    public function generate_page_planets_is_null_at_stars_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::StarsGenerated]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('planets', null));
    }

    #[Test]
    public function generate_page_planets_has_count_when_planets_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::PlanetsGenerated]);
        $star = Star::factory()->create(['game_id' => $game->id]);
        Planet::factory()->count(2)->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('planets.count', 2));
    }

    #[Test]
    public function generate_page_deposits_is_null_before_deposits_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::PlanetsGenerated]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('deposits', null));
    }

    #[Test]
    public function generate_page_members_are_players_only_with_empire_info(): void
    {
        $game = Game::factory()->create();
        $gm = $this->gmUser($game);
        $player = User::factory()->create();
        $game->users()->attach($player, ['role' => 'player', 'is_active' => true]);

        $this->actingAs($gm)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('members', 1)
                ->where('members.0.id', $player->id)
                ->where('members.0.empire', null)
            );
    }

    #[Test]
    public function generate_page_home_systems_list_includes_location_and_capacity(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::HomeSystemGenerated]);
        $star = Star::factory()->create(['game_id' => $game->id, 'x' => 5, 'y' => 12, 'z' => 0]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        HomeSystem::factory()->create([
            'game_id' => $game->id,
            'star_id' => $star->id,
            'homeworld_planet_id' => $planet->id,
            'queue_position' => 1,
        ]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('homeSystems', 1)
                ->where('homeSystems.0.queue_position', 1)
                ->where('homeSystems.0.star_location', '05-12-00')
                ->where('homeSystems.0.empire_count', 0)
                ->where('homeSystems.0.capacity', 25)
            );
    }

    // -------------------------------------------------------------------------
    // uploadHomeSystemTemplate
    // -------------------------------------------------------------------------

    #[Test]
    public function upload_home_system_template_creates_template_and_children(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('template.json', $this->makeHomeSystemTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertRedirect();

        $this->assertNotNull($game->fresh()->homeSystemTemplate);
        $this->assertSame(1, $game->homeSystemTemplate()->first()->planets()->count());
    }

    #[Test]
    public function upload_home_system_template_replaces_existing_template(): void
    {
        $game = Game::factory()->withDefaultTemplates()->create();
        $user = $this->gmUser($game);

        $existingTemplate = $game->homeSystemTemplate;
        $file = $this->jsonFile('template.json', $this->makeHomeSystemTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertRedirect();

        $this->assertModelMissing($existingTemplate);
        $this->assertSame(1, $game->homeSystemTemplate()->first()->planets()->count());
    }

    #[Test]
    public function upload_home_system_template_is_rejected_when_game_is_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $user = $this->gmUser($game);

        $file = $this->jsonFile('template.json', $this->makeHomeSystemTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function upload_home_system_template_is_rejected_when_no_planets(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('template.json', json_encode(['planets' => []]));

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function upload_home_system_template_is_rejected_when_no_homeworld(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $json = json_encode(['planets' => [
            ['orbit' => 1, 'type' => 'terrestrial', 'habitability' => 10, 'homeworld' => false, 'deposits' => []],
        ]]);

        $file = $this->jsonFile('template.json', $json);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function upload_home_system_template_is_rejected_when_multiple_homeworlds(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('template.json', $this->makeHomeSystemTemplateJson(planetCount: 2, homeworldCount: 2));

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/home-system", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    // -------------------------------------------------------------------------
    // uploadColonyTemplate
    // -------------------------------------------------------------------------

    #[Test]
    public function upload_colony_template_creates_template_and_items(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('colony.json', $this->makeColonyTemplateJson(itemCount: 3));

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file])
            ->assertRedirect();

        $this->assertNotNull($game->fresh()->colonyTemplate);
        $this->assertSame(3, $game->colonyTemplate()->first()->items()->count());
    }

    #[Test]
    public function upload_colony_template_replaces_existing_template(): void
    {
        $game = Game::factory()->withDefaultTemplates()->create();
        $user = $this->gmUser($game);

        $existingTemplate = $game->colonyTemplate;
        $file = $this->jsonFile('colony.json', $this->makeColonyTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file])
            ->assertRedirect();

        $this->assertModelMissing($existingTemplate);
        $this->assertSame(1, $game->colonyTemplate()->first()->items()->count());
    }

    #[Test]
    public function upload_colony_template_is_rejected_when_game_is_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $user = $this->gmUser($game);

        $file = $this->jsonFile('colony.json', $this->makeColonyTemplateJson());

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    #[Test]
    public function upload_colony_template_is_rejected_when_no_inventory_items(): void
    {
        $game = Game::factory()->create();
        $user = $this->gmUser($game);

        $file = $this->jsonFile('colony.json', json_encode(['Kind' => 1, 'TechLevel' => 1, 'inventory' => []]));

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/templates/colony", ['template' => $file])
            ->assertSessionHasErrors('template');
    }

    // -------------------------------------------------------------------------
    // generateStars
    // -------------------------------------------------------------------------

    #[Test]
    public function generate_stars_creates_100_stars_and_redirects(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/stars")
            ->assertRedirect();

        $this->assertSame(100, $game->stars()->count());
        $this->assertSame(GameStatus::StarsGenerated, $game->fresh()->status);
    }

    #[Test]
    public function generate_stars_writes_generation_step_record(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/stars");

        $step = $game->generationSteps()->first();
        $this->assertNotNull($step);
        $this->assertSame(GenerationStepName::Stars, $step->step);
    }

    #[Test]
    public function generate_stars_accepts_seed_override(): void
    {
        $gameA = Game::factory()->create(['status' => GameStatus::Setup, 'prng_seed' => 'seed-a']);
        $gameB = Game::factory()->create(['status' => GameStatus::Setup, 'prng_seed' => 'seed-a']);
        $user = $this->gmUser($gameA);
        $gameB->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        $this->actingAs($user)->post("/games/{$gameA->id}/generate/stars");
        $this->actingAs($user)->post("/games/{$gameB->id}/generate/stars", ['seed' => 'override-seed']);

        $starsA = $gameA->stars()->get(['x', 'y', 'z'])->toArray();
        $starsB = $gameB->stars()->get(['x', 'y', 'z'])->toArray();

        $this->assertNotSame($starsA, $starsB);
    }

    #[Test]
    public function generate_stars_seed_override_does_not_change_game_prng_seed(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup, 'prng_seed' => 'original']);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/stars", ['seed' => 'override']);

        $this->assertSame('original', $game->fresh()->prng_seed);
    }

    #[Test]
    public function generate_stars_is_forbidden_for_non_gm(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/stars")
            ->assertForbidden();
    }

    #[Test]
    public function generate_stars_is_rejected_when_status_is_not_setup(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::StarsGenerated]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/stars")
            ->assertSessionHasErrors('seed');
    }
}
